<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Http\Controller;

use App\Inventory\Application\Command\ReceiveStockManually;
use App\Inventory\Application\Query\GetInventoryItemDetail;
use App\Inventory\Application\Query\View\FacilityStockBlockView;
use App\Inventory\Application\Query\View\InventoryItemDetailView;
use App\Inventory\Domain\Exception\ConcurrentInventoryItemModification;
use App\Inventory\Domain\Exception\InventoryItemNotFound;
use App\Inventory\Infrastructure\Http\Form\ReceiveStockFormType;
use App\Inventory\Infrastructure\Http\Form\ReceiveStockInput;
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
 * HTTP adapter for the LRA-87 "Receive Stock" HTMX dialog.
 *
 * Thin controller: it loads the inventory item detail to populate the
 * facility picker, binds the operator's input to a primitive command DTO,
 * normalises the per-unit / total cost XOR pair before dispatching, and
 * translates domain failures into either field-level form errors (HTTP
 * 422) or a stable 409 with a friendly notice when the aggregate was
 * modified concurrently. On success the response is an empty 200 with
 * `HX-Trigger: stockReceived` so the inventory list page auto-refreshes.
 */
final class ReceiveStockController extends AbstractController
{
    use HandleTrait {
        handle as private dispatchCommand;
    }

    private const string UUID_V7_REQUIREMENT = '[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}';

    private const string DIALOG_TEMPLATE = 'inventory/receive/_dialog.html.twig';

    private const string ITEM_NOT_FOUND = 'Inventory item not found.';

    private const string GENERIC_RECEIVE_FAILURE = 'Unable to record stock receipt. Please try again.';

    private const string CONCURRENT_NOTICE = 'Item changed since you loaded the page — refreshing.';

    private const string MODAL_TITLE = 'Receive Stock';

    private const string SUBMIT_LABEL = 'Record Receipt';

    private const string ERR_COST_BOTH = 'Provide a per-unit cost or a total cost, not both.';

    private const string ERR_COST_REQUIRED = 'Cost is required.';

    private const string ERR_QUANTITY_FOR_TOTAL = 'Enter a quantity before specifying a total cost.';

    public function __construct(
        MessageBusInterface $commandBus,
        private readonly MessageBusInterface $queryBus,
    ) {
        $this->messageBus = $commandBus;
    }

    #[Route(
        '/admin/inventory/{itemId}/receive',
        name: 'inventory_receive_form',
        requirements: ['itemId' => self::UUID_V7_REQUIREMENT],
        methods: ['GET'],
    )]
    #[IsGranted('receive_stock')]
    public function newForm(string $itemId): Response
    {
        try {
            $detail = $this->loadDetail($itemId);
        } catch (InventoryItemNotFound) {
            throw $this->createNotFoundException(self::ITEM_NOT_FOUND);
        }

        $form = $this->createForm(
            ReceiveStockFormType::class,
            new ReceiveStockInput(),
            [ReceiveStockFormType::FACILITY_CHOICES_OPTION => $this->facilityChoicesFrom($detail)],
        );

        return $this->renderDialog($form, $itemId);
    }

    #[Route(
        '/admin/inventory/{itemId}/receive',
        name: 'inventory_receive_submit',
        requirements: ['itemId' => self::UUID_V7_REQUIREMENT],
        methods: ['POST'],
    )]
    #[IsGranted('receive_stock')]
    public function submit(string $itemId, Request $request): Response
    {
        try {
            $detail = $this->loadDetail($itemId);
        } catch (InventoryItemNotFound) {
            throw $this->createNotFoundException(self::ITEM_NOT_FOUND);
        }

        $facilityChoices = $this->facilityChoicesFrom($detail);
        $input = new ReceiveStockInput();
        $form = $this->createForm(
            ReceiveStockFormType::class,
            $input,
            [ReceiveStockFormType::FACILITY_CHOICES_OPTION => $facilityChoices],
        );
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->renderDialog($form, $itemId, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        [$costInvalid, $costNote] = $this->normalizeCost($form, $input);
        if ($costInvalid) {
            return $this->renderDialog($form, $itemId, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->dispatchReceive($itemId, $input, $costNote, $form);
    }

    /**
     * Dispatches the receive command and maps the two recoverable failures
     * back to the dialog: a concurrent-modification conflict (409, with an
     * outerHTML reswap) or any other failure (422). Returns the saved
     * response on success.
     *
     * @template TData
     *
     * @param FormInterface<TData> $form
     */
    private function dispatchReceive(
        string $itemId,
        ReceiveStockInput $input,
        string $costNote,
        FormInterface $form,
    ): Response {
        try {
            $this->dispatchCommandUnwrapping(new ReceiveStockManually(
                itemId: $itemId,
                facilityCode: (string) $input->facilityCode,
                quantityUnits: (int) $input->quantityUnits,
                costPerUnitCents: (int) $input->costPerUnitCents,
                comment: $this->buildFinalComment($input->comment, $costNote),
                purchaseOrderLineId: null,
            ));
        } catch (ConcurrentInventoryItemModification) {
            $form->addError(new FormError(self::CONCURRENT_NOTICE));
            $response = $this->renderDialog($form, $itemId, Response::HTTP_CONFLICT);
            $response->headers->set('Hx-Reswap', 'outerHTML');

            return $response;
        } catch (Throwable) {
            $form->addError(new FormError(self::GENERIC_RECEIVE_FAILURE));

            return $this->renderDialog($form, $itemId, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->savedResponse();
    }

    private function loadDetail(string $itemId): InventoryItemDetailView
    {
        $envelope = $this->queryBus->dispatch(new GetInventoryItemDetail($itemId));
        $stamp = $envelope->last(HandledStamp::class);
        if ($stamp === null) {
            throw new LogicException('GetInventoryItemDetail returned no HandledStamp.');
        }
        $result = $stamp->getResult();
        if (!$result instanceof InventoryItemDetailView) {
            throw new LogicException(sprintf(
                'GetInventoryItemDetail returned %s, expected %s.',
                get_debug_type($result),
                InventoryItemDetailView::class,
            ));
        }
        return $result;
    }

    /**
     * @return array<string, string> label => facility code
     */
    private function facilityChoicesFrom(InventoryItemDetailView $detail): array
    {
        $choices = [];
        foreach ($detail->facilityStockBlocks as $block) {
            // FacilityStockBlockView only carries the code; the label is
            // the code itself (no separate display-name registry yet).
            $code = $block->facilityCode;
            $choices[$code] = $code;
        }

        // If the item has never received stock, surface the default
        // operator-typed facility so the modal is still actionable.
        if ($choices === []) {
            $choices['MAIN'] = 'MAIN';
        }

        ksort($choices);
        return $choices;
    }

    /**
     * Implements the per-unit / total-cost XOR contract.
     *
     * Returns a two-tuple `[invalid, note]`:
     *   - invalid: true when the controller added a field-level error
     *     and the caller should re-render the dialog as 422.
     *   - note: optional human-readable suffix (e.g. "(0 cent remainder)")
     *     the controller appends to the operator's comment so any
     *     intdiv remainder is preserved on the audit trail.
     *
     * @template TData
     * @param FormInterface<TData> $form
     * @return array{0: bool, 1: string}
     */
    private function normalizeCost(FormInterface $form, ReceiveStockInput $input): array
    {
        $perUnit = $input->costPerUnitCents;
        $total = $input->totalCostCents;

        if ($perUnit !== null && $total !== null) {
            $form->get('costPerUnitCents')->addError(new FormError(self::ERR_COST_BOTH));
            $form->get('totalCostCents')->addError(new FormError(self::ERR_COST_BOTH));
            return [true, ''];
        }

        if ($perUnit === null && $total === null) {
            $form->get('costPerUnitCents')->addError(new FormError(self::ERR_COST_REQUIRED));
            return [true, ''];
        }

        return $this->resolveCostFromTotal($form, $input, $total);
    }

    /**
     * Resolves the per-unit cost when only the total was supplied. When the
     * per-unit value was supplied directly ($total is null) there is nothing
     * to derive. Mutates $input->costPerUnitCents with the derived value and
     * returns the same [invalid, note] tuple as {@see normalizeCost()}.
     *
     * @template TData
     *
     * @param FormInterface<TData> $form
     *
     * @return array{0: bool, 1: string}
     */
    private function resolveCostFromTotal(FormInterface $form, ReceiveStockInput $input, ?int $total): array
    {
        if ($total === null) {
            return [false, ''];
        }

        $quantity = $input->quantityUnits;
        if ($quantity === null || $quantity < 1) {
            $form->get('totalCostCents')->addError(new FormError(self::ERR_QUANTITY_FOR_TOTAL));
            return [true, ''];
        }

        $derived = intdiv($total, $quantity);
        $remainder = $total - ($derived * $quantity);
        $input->costPerUnitCents = $derived;

        // Two separate ternaries (not nested) keep this at one return.
        $unit = $remainder === 1 ? 'cent' : 'cents';
        $note = $remainder > 0 ? sprintf('(%d %s remainder)', $remainder, $unit) : '';

        return [false, $note];
    }

    private function buildFinalComment(?string $operatorComment, string $costNote): ?string
    {
        $parts = array_filter(
            [$operatorComment !== null ? trim($operatorComment) : '', $costNote],
            static fn (string $part): bool => $part !== '',
        );

        return $parts === [] ? null : implode(' ', $parts);
    }

    /**
     * @template TData
     * @param FormInterface<TData> $form
     */
    private function renderDialog(FormInterface $form, string $itemId, int $status = Response::HTTP_OK): Response
    {
        return $this->render(
            self::DIALOG_TEMPLATE,
            [
                'form' => $form->createView(),
                'formAction' => $this->generateUrl('inventory_receive_submit', ['itemId' => $itemId]),
                'modalTitle' => self::MODAL_TITLE,
                'submitLabel' => self::SUBMIT_LABEL,
                'maxCommentLength' => ReceiveStockFormType::MAX_COMMENT_LENGTH,
            ],
            new Response(null, $status),
        );
    }

    private function savedResponse(): Response
    {
        $response = new Response('', Response::HTTP_OK);
        $response->headers->set('HX-Trigger', 'stockReceived');
        $response->headers->set('HX-Reswap', 'none');
        return $response;
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
}
