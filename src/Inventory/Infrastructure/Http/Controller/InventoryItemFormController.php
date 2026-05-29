<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Http\Controller;

use App\Catalog\Application\Command\RenameListing;
use App\Catalog\Application\Command\UpdateListingFees;
use App\Catalog\Application\Command\UpdateListingLedgerAccount;
use App\Catalog\Application\Command\UpdateListingTaxTreatment;
use App\Catalog\Application\Query\GetListingDetail;
use App\Catalog\Application\Query\View\FeeView;
use App\Catalog\Application\Query\View\ListingDetailView;
use App\Inventory\Application\Command\RegisterInventoryItem;
use App\Inventory\Application\Command\UpdateInventoryItemSettings;
use App\Inventory\Application\Exception\CrossBusRegistrationFailed;
use App\Inventory\Application\Query\GetInventoryItemDetail;
use App\Inventory\Application\Query\View\InventoryItemDetailView;
use App\Inventory\Domain\Barcode\BarcodeRenderer;
use App\Inventory\Domain\Exception\InvalidPosColor;
use App\Inventory\Domain\Exception\InventoryItemNotFound;
use App\Inventory\Domain\Exception\VendorNotFound;
use App\Inventory\Infrastructure\Http\Form\InventoryItemFormType;
use App\Inventory\Infrastructure\Http\Form\InventoryItemInput;
use LogicException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Throwable;

/**
 * HTTP adapter for the LRA-86 "Create / Edit Inventory Item" HTMX
 * dialog and the matching CODE128 barcode print page. The controller
 * is intentionally thin: it builds a primitive command DTO, dispatches
 * it via the configured `command.bus`, translates domain failures into
 * either field-level form errors (HTTP 422) or stable status codes
 * (404 for unknown item), and on success returns an empty 200 with
 * `HX-Trigger: inventoryItemSaved` so the page's `#inventory-table`
 * region auto-refreshes and the modal dismisses itself.
 *
 * Stock-batch preservation: the Edit path never invokes archive() or
 * any batch mutator on the aggregate. {@see UpdateInventoryItemSettings}
 * touches only the per-item editable fields; the StockBatch collection
 * is left untouched. The functional test asserts this contract.
 */
final class InventoryItemFormController extends AbstractController
{
    use HandleTrait {
        handle as private dispatchCommand;
    }

    private const string UUID_V7_REQUIREMENT = '[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}';

    private const string DEFAULT_FEE_CURRENCY = 'USD';

    private const string DEFAULT_FEE_LABEL = 'Base';

    private const string DIALOG_TEMPLATE = 'inventory/item/_dialog.html.twig';

    private const string NOT_FOUND_MESSAGE = 'Inventory item not found.';

    private const string CREATE_TITLE = 'New Inventory Item';

    private const string CREATE_SUBMIT = 'Create Item';

    private const string EDIT_TITLE = 'Edit Inventory Item';

    private const string EDIT_SUBMIT = 'Save Changes';

    private const string GENERIC_SAVE_FAILURE = 'Unable to save inventory item. Please try again.';

    public function __construct(
        MessageBusInterface $commandBus,
        private readonly MessageBusInterface $queryBus,
        private readonly BarcodeRenderer $barcodeRenderer,
    ) {
        $this->messageBus = $commandBus;
    }

    #[Route('/admin/inventory/new', name: 'inventory_item_new', methods: ['GET'])]
    #[IsGranted('manage_inventory')]
    public function newForm(): Response
    {
        $form = $this->createForm(InventoryItemFormType::class, new InventoryItemInput());

        return $this->render(self::DIALOG_TEMPLATE, [
            'form' => $form->createView(),
            'mode' => 'create',
            'formAction' => $this->generateUrl('inventory_item_create'),
            'modalTitle' => self::CREATE_TITLE,
            'submitLabel' => self::CREATE_SUBMIT,
        ]);
    }

    #[Route('/admin/inventory/new', name: 'inventory_item_create', methods: ['POST'])]
    #[IsGranted('manage_inventory')]
    public function create(Request $request): Response
    {
        $input = new InventoryItemInput();
        $form = $this->createForm(InventoryItemFormType::class, $input);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->reRenderForm(
                $form,
                'create',
                $this->generateUrl('inventory_item_create'),
                self::CREATE_TITLE,
                self::CREATE_SUBMIT,
            );
        }

        try {
            $this->dispatchCommandUnwrapping($this->buildRegisterCommand($input));
        } catch (CrossBusRegistrationFailed $failure) {
            $this->applyCrossBusFailureToForm($form, $failure);

            return $this->reRenderForm(
                $form,
                'create',
                $this->generateUrl('inventory_item_create'),
                self::CREATE_TITLE,
                self::CREATE_SUBMIT,
            );
        }

        return $this->savedResponse();
    }

    #[Route(
        '/admin/inventory/{itemId}/edit',
        name: 'inventory_item_edit',
        requirements: ['itemId' => self::UUID_V7_REQUIREMENT],
        methods: ['GET'],
    )]
    #[IsGranted('manage_inventory')]
    public function editForm(string $itemId): Response
    {
        try {
            [$detail, $listing] = $this->loadItemAndListing($itemId);
        } catch (InventoryItemNotFound) {
            throw $this->createNotFoundException(self::NOT_FOUND_MESSAGE);
        }

        $input = $this->inputFrom($detail, $listing);
        $form = $this->createForm(InventoryItemFormType::class, $input);

        return $this->render(self::DIALOG_TEMPLATE, [
            'form' => $form->createView(),
            'mode' => 'edit',
            'formAction' => $this->generateUrl('inventory_item_update', ['itemId' => $itemId]),
            'modalTitle' => self::EDIT_TITLE,
            'submitLabel' => self::EDIT_SUBMIT,
        ]);
    }

    #[Route(
        '/admin/inventory/{itemId}/edit',
        name: 'inventory_item_update',
        requirements: ['itemId' => self::UUID_V7_REQUIREMENT],
        methods: ['POST'],
    )]
    #[IsGranted('manage_inventory')]
    public function update(string $itemId, Request $request): Response
    {
        try {
            // We only need the Catalog Listing projection here — the
            // Inventory-side fields will be re-loaded inside the
            // UpdateInventoryItemSettings handler. The discarded
            // InventoryItemDetailView also doubles as an existence
            // probe: if the item is missing, the query throws.
            [, $listing] = $this->loadItemAndListing($itemId);
        } catch (InventoryItemNotFound) {
            throw $this->createNotFoundException(self::NOT_FOUND_MESSAGE);
        }

        $input = new InventoryItemInput();
        $form = $this->createForm(InventoryItemFormType::class, $input);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->dispatchCommandUnwrapping(new UpdateInventoryItemSettings(
                    itemId: $itemId,
                    posColorHex: (string) $input->posColorHex,
                    primaryVendorId: self::normalizeVendorId($input->vendorId),
                    trackInventory: $input->trackInventory,
                    rentable: $input->rentable,
                    reorderThresholdUnits: (int) $input->reorderThresholdUnits,
                ));

                $this->dispatchListingUpdates($listing, $input);

                return $this->savedResponse();
            } catch (InvalidPosColor $exception) {
                $form->get('posColorHex')->addError(new FormError($exception->getMessage()));
            } catch (VendorNotFound $exception) {
                $form->get('vendorId')->addError(new FormError($exception->getMessage()));
            } catch (Throwable) {
                $form->addError(new FormError(self::GENERIC_SAVE_FAILURE));
            }
        }

        return $this->reRenderForm(
            $form,
            'edit',
            $this->generateUrl('inventory_item_update', ['itemId' => $itemId]),
            self::EDIT_TITLE,
            self::EDIT_SUBMIT,
        );
    }

    #[Route(
        '/admin/inventory/{itemId}/barcode',
        name: 'inventory_item_barcode',
        requirements: ['itemId' => self::UUID_V7_REQUIREMENT],
        methods: ['GET'],
    )]
    #[IsGranted('manage_inventory')]
    public function barcode(string $itemId): Response
    {
        try {
            [$detail, $listing] = $this->loadItemAndListing($itemId);
        } catch (InventoryItemNotFound) {
            throw $this->createNotFoundException(self::NOT_FOUND_MESSAGE);
        }

        return $this->render('inventory/item/_barcode_print.html.twig', [
            'item' => $detail,
            'listing' => $listing,
            'barcodeHtml' => $this->barcodeRenderer->renderHtml($listing->code),
        ]);
    }

    /**
     * @return array{0: InventoryItemDetailView, 1: ListingDetailView}
     */
    private function loadItemAndListing(string $itemId): array
    {
        $detail = $this->runQuery(new GetInventoryItemDetail($itemId), InventoryItemDetailView::class);
        $listing = $this->runQuery(new GetListingDetail($detail->listingId), ListingDetailView::class);

        return [$detail, $listing];
    }

    /**
     * @template TResult of object
     * @param class-string<TResult> $expected
     * @return TResult
     */
    private function runQuery(object $query, string $expected): object
    {
        $envelope = $this->queryBus->dispatch($query);

        $stamp = $envelope->last(HandledStamp::class);
        if ($stamp === null) {
            throw new LogicException('Query handler returned no HandledStamp.');
        }

        $result = $stamp->getResult();
        if (!$result instanceof $expected) {
            throw new LogicException(sprintf(
                'Query returned %s, expected %s.',
                get_debug_type($result),
                $expected,
            ));
        }

        return $result;
    }

    private function inputFrom(InventoryItemDetailView $detail, ListingDetailView $listing): InventoryItemInput
    {
        $input = new InventoryItemInput();
        $input->name = $listing->name;
        $input->code = $listing->code;
        $input->kind = $listing->kind;
        $input->vendorId = $detail->primaryVendorId;
        $input->posColorHex = $detail->posColorHex;
        $input->ledgerAccount = $listing->ledgerAccount;
        $input->taxApply = $listing->taxApply;
        $input->taxIncludedInFee = $listing->taxIncludedInFee;
        $input->feeAmountCents = $this->primaryFeeCents($listing);
        $input->trackInventory = $detail->tracksInventory;
        $input->rentable = $detail->rentable;
        $input->reorderThresholdUnits = $detail->reorderThresholdUnits ?? 0;

        return $input;
    }

    private function primaryFeeCents(ListingDetailView $listing): int
    {
        foreach ($listing->fees as $fee) {
            return $fee->amountCents;
        }
        return 0;
    }

    private function buildRegisterCommand(InventoryItemInput $input): RegisterInventoryItem
    {
        return new RegisterInventoryItem(
            code: (string) $input->code,
            name: (string) $input->name,
            kind: (string) $input->kind,
            fees: [[
                'amountCents' => (int) $input->feeAmountCents,
                'currency' => self::DEFAULT_FEE_CURRENCY,
                'label' => self::DEFAULT_FEE_LABEL,
            ]],
            taxApply: $input->taxApply,
            taxIncludedInFee: $input->taxIncludedInFee,
            ledgerAccount: (string) $input->ledgerAccount,
            primaryVendorId: self::normalizeVendorId($input->vendorId),
            posColorHex: (string) $input->posColorHex,
            trackInventory: $input->trackInventory,
            rentable: $input->rentable,
            reorderThresholdUnits: (int) $input->reorderThresholdUnits,
        );
    }

    private function dispatchListingUpdates(ListingDetailView $current, InventoryItemInput $input): void
    {
        if ($current->name !== (string) $input->name) {
            $this->dispatchCommandUnwrapping(new RenameListing(
                listingId: $current->id,
                name: (string) $input->name,
            ));
        }

        $nextFees = [[
            'amountCents' => (int) $input->feeAmountCents,
            'currency' => self::DEFAULT_FEE_CURRENCY,
            'label' => self::DEFAULT_FEE_LABEL,
        ]];
        if (!$this->feesEqual($current->fees, $nextFees)) {
            $this->dispatchCommandUnwrapping(new UpdateListingFees(
                listingId: $current->id,
                fees: $nextFees,
            ));
        }

        if ($current->ledgerAccount !== (string) $input->ledgerAccount) {
            $this->dispatchCommandUnwrapping(new UpdateListingLedgerAccount(
                listingId: $current->id,
                ledgerAccount: (string) $input->ledgerAccount,
            ));
        }

        if (
            $current->taxApply !== $input->taxApply
            || $current->taxIncludedInFee !== $input->taxIncludedInFee
        ) {
            $this->dispatchCommandUnwrapping(new UpdateListingTaxTreatment(
                listingId: $current->id,
                taxApply: $input->taxApply,
                taxIncludedInFee: $input->taxIncludedInFee,
            ));
        }
    }

    /**
     * @param list<FeeView> $current
     * @param list<array{amountCents:int,currency:string,label:string}> $next
     */
    private function feesEqual(array $current, array $next): bool
    {
        if (count($current) !== count($next)) {
            return false;
        }
        foreach ($current as $index => $fee) {
            $target = $next[$index];
            if (
                $fee->amountCents !== $target['amountCents']
                || $fee->currency !== $target['currency']
                || $fee->label !== $target['label']
            ) {
                return false;
            }
        }
        return true;
    }

    /**
     * Treats a missing or blank-string vendor id from the form input as
     * "no primary vendor". The form binds an empty `<input>` to an empty
     * string and the field is also marked optional, so both shapes need
     * the same fallback to null before reaching the command DTOs.
     */
    private static function normalizeVendorId(?string $vendorId): ?string
    {
        return $vendorId !== null && $vendorId !== '' ? $vendorId : null;
    }

    /**
     * Messenger wraps handler exceptions in HandlerFailedException; unwrap
     * to surface the original domain exception to the caller.
     */
    private function dispatchCommandUnwrapping(object $command): mixed
    {
        try {
            return $this->dispatchCommand($command);
        } catch (HandlerFailedException $wrapper) {
            $nested = $wrapper->getPrevious();
            if ($nested instanceof Throwable) {
                throw $nested;
            }
            throw $wrapper;
        }
    }

    /**
     * Inspects a {@see CrossBusRegistrationFailed} cause chain and
     * maps it to either a field-level error or a top-of-form message.
     *
     * @template TData
     * @param FormInterface<TData> $form
     */
    private function applyCrossBusFailureToForm(FormInterface $form, CrossBusRegistrationFailed $failure): void
    {
        $cause = $failure->getPrevious();
        $causeClass = $cause !== null ? $cause::class : '';

        // Inspect by FQCN string rather than by `instanceof` against the
        // imported class so the Inventory Infrastructure layer does not
        // pull a hard dependency on Catalog Domain exception types
        // (deptrac forbids that boundary crossing). The exception
        // markers are stable contract surface — renaming any of them
        // would be a deliberate, reviewed change.
        if (
            $cause instanceof Throwable
            && $causeClass === 'App\\Catalog\\Domain\\Exception\\DuplicateListingCode'
            && $form->has('code')
        ) {
            $form->get('code')->addError(new FormError($cause->getMessage()));
            return;
        }

        if ($cause instanceof InvalidPosColor && $form->has('posColorHex')) {
            $form->get('posColorHex')->addError(new FormError($cause->getMessage()));
            return;
        }

        if ($cause instanceof VendorNotFound && $form->has('vendorId')) {
            $form->get('vendorId')->addError(new FormError($cause->getMessage()));
            return;
        }

        $form->addError(new FormError(self::GENERIC_SAVE_FAILURE));
    }

    /**
     * @template TData
     * @param FormInterface<TData> $form
     */
    private function reRenderForm(
        FormInterface $form,
        string $mode,
        string $formAction,
        string $modalTitle,
        string $submitLabel,
    ): Response {
        return $this->render(
            self::DIALOG_TEMPLATE,
            [
                'form' => $form->createView(),
                'mode' => $mode,
                'formAction' => $formAction,
                'modalTitle' => $modalTitle,
                'submitLabel' => $submitLabel,
            ],
            new Response(null, Response::HTTP_UNPROCESSABLE_ENTITY),
        );
    }

    private function savedResponse(): Response
    {
        $response = new Response('', Response::HTTP_OK);
        $response->headers->set('HX-Trigger', 'inventoryItemSaved');
        $response->headers->set('HX-Reswap', 'none');
        return $response;
    }
}
