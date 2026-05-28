<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Http\Controller;

use App\Inventory\Application\Command\AdjustStock;
use App\Inventory\Application\Query\ListInventory;
use App\Inventory\Application\Query\View\InventoryListPage;
use App\Inventory\Domain\Exception\ConcurrentInventoryItemModification;
use App\Inventory\Domain\Exception\InvalidFacilityCode;
use App\Inventory\Domain\ValueObject\FacilityCode;
use App\Inventory\Domain\ValueObject\StockAdjustmentReason;
use App\Inventory\Infrastructure\Http\Form\TakeInventoryFormType;
use App\Inventory\Infrastructure\Http\Form\TakeInventoryInput;
use App\Inventory\Infrastructure\Http\Form\TakeInventoryLineFormType;
use App\Inventory\Infrastructure\Http\Form\TakeInventoryLineInput;
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
 * HTTP adapter for the LRA-87 "Take Inventory" bulk grid.
 *
 * Three routes share one query dispatch ({@see ListInventory}):
 *   - GET  /admin/inventory/take          (`inventory_take_index`)
 *     renders the full page. When `?facilityCode=` is missing the page
 *     shows a facility picker instead of an empty grid.
 *   - GET  /admin/inventory/take/_grid    (`inventory_take_grid`)
 *     returns the grid partial for HTMX swap.
 *   - POST /admin/inventory/take          (`inventory_take_submit`)
 *     processes the bulk submit.
 *
 * Submit semantics: validation rejection is atomic — any variance row
 * missing a reason causes the entire submit to return 422 with no
 * AdjustStock dispatches. The per-command happy-path dispatch is
 * intentionally non-atomic (per the ticket spec): each AdjustStock runs
 * in its own transaction, so a concurrent-modification failure on row
 * N does not roll back the rows that already committed. The grid is
 * re-rendered with per-row errors when that happens.
 */
final class TakeInventoryController extends AbstractController
{
    use HandleTrait {
        handle as private dispatchCommand;
    }

    private const string PAGE_TEMPLATE = 'inventory/take/index.html.twig';

    private const string GRID_TEMPLATE = 'inventory/take/_grid.html.twig';

    private const string GENERIC_ADJUST_FAILURE = 'Unable to post inventory adjustments. Please try again.';

    private const string ERR_REASON_REQUIRED_FMT = 'Reason required for variance on %s.';

    private const string ERR_CONCURRENT_FMT = 'Stock changed for %s — review and re-submit.';

    private const string ERR_FACILITY_REQUIRED = 'Choose a facility before posting adjustments.';

    private const int GRID_PAGE_SIZE = 500;

    public function __construct(
        MessageBusInterface $commandBus,
        private readonly MessageBusInterface $queryBus,
    ) {
        $this->messageBus = $commandBus;
    }

    #[Route('/admin/inventory/take', name: 'inventory_take_index', methods: ['GET'])]
    #[IsGranted('take_inventory')]
    public function index(Request $request): Response
    {
        $facility = $this->normalizedFacility($request);

        if ($facility === null) {
            return $this->render(self::PAGE_TEMPLATE, [
                'facilityCode' => null,
                'form' => null,
                'rowErrors' => [],
                'topError' => null,
                'maxReasonNoteLength' => TakeInventoryLineFormType::MAX_REASON_NOTE_LENGTH,
            ]);
        }

        $form = $this->buildPopulatedForm($facility);

        return $this->render(self::PAGE_TEMPLATE, [
            'facilityCode' => $facility,
            'form' => $form->createView(),
            'rowErrors' => [],
            'topError' => null,
            'maxReasonNoteLength' => TakeInventoryLineFormType::MAX_REASON_NOTE_LENGTH,
        ]);
    }

    #[Route('/admin/inventory/take/_grid', name: 'inventory_take_grid', methods: ['GET'])]
    #[IsGranted('take_inventory')]
    public function grid(Request $request): Response
    {
        $facility = $this->normalizedFacility($request);
        if ($facility === null) {
            return new Response(self::ERR_FACILITY_REQUIRED, Response::HTTP_BAD_REQUEST);
        }

        $form = $this->buildPopulatedForm($facility);
        return $this->renderGrid($form, $facility, [], null);
    }

    #[Route('/admin/inventory/take', name: 'inventory_take_submit', methods: ['POST'])]
    #[IsGranted('take_inventory')]
    public function submit(Request $request): Response
    {
        $facility = $this->normalizedFacility($request);
        if ($facility === null) {
            return new Response(self::ERR_FACILITY_REQUIRED, Response::HTTP_BAD_REQUEST);
        }

        // Re-build the form from the current ListInventory projection so
        // the CollectionType already has one entry per row before the
        // submitted data is bound. With `allow_add: false` an empty
        // initial collection would reject every posted row as an
        // "extra field". The bound submission then overwrites the
        // pre-populated values for the rows the operator changed.
        // Capture an authoritative server snapshot BEFORE binding the
        // submission so the security-sensitive fields (itemId, expected,
        // listingCode) can never be tampered with via hidden HTML
        // inputs. We rebuild the form from the snapshot, bind the
        // submission (which lets the operator-supplied actual / reason /
        // reasonNote land on the DTO), then immediately overwrite each
        // line's itemId / expected / listingCode back to the snapshot
        // values matched by row index. Any row whose index falls
        // outside the snapshot is dropped — that row didn't exist
        // server-side, so it cannot legitimately come from a real
        // submission.
        $snapshot = $this->runListInventory($facility);
        $form = $this->createForm(TakeInventoryFormType::class, $this->seedInputFrom($snapshot));
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->renderGrid($form, $facility, [], null, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        /** @var TakeInventoryInput $input */
        $input = $form->getData();
        $this->overwriteWithSnapshot($input, $snapshot);

        // Atomic validation pass: every variance row must carry a reason.
        $atomicallyRejected = $this->validateVariancesAtomically($form, $input);
        if ($atomicallyRejected) {
            return $this->renderGrid($form, $facility, [], null, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Dispatch one AdjustStock per variance row. Each command runs in
        // its own transaction — committed rows are NOT rolled back when
        // a later row hits a concurrent-modification race.
        $rowErrors = $this->dispatchVarianceRows($input, $facility);

        if ($rowErrors !== []) {
            return $this->renderGrid(
                $form,
                $facility,
                $rowErrors,
                null,
                Response::HTTP_CONFLICT,
            );
        }

        return $this->adjustedResponse();
    }

    private function normalizedFacility(Request $request): ?string
    {
        $raw = $request->query->get('facilityCode');
        if (!is_string($raw)) {
            return null;
        }
        $trimmed = trim($raw);
        if ($trimmed === '') {
            return null;
        }
        try {
            return FacilityCode::fromString($trimmed)->value;
        } catch (InvalidFacilityCode) {
            return null;
        }
    }

    /**
     * @return FormInterface<TakeInventoryInput>
     */
    private function buildPopulatedForm(string $facility): FormInterface
    {
        $input = $this->seedInputFrom($this->runListInventory($facility));
        return $this->createForm(TakeInventoryFormType::class, $input);
    }

    private function seedInputFrom(InventoryListPage $page): TakeInventoryInput
    {
        $input = new TakeInventoryInput();
        foreach ($page->items as $item) {
            $line = new TakeInventoryLineInput();
            $line->itemId = $item->inventoryItemId;
            $line->listingCode = $item->listingCode;
            $line->expected = $item->totalQuantityOnHand;
            $line->actual = $item->totalQuantityOnHand;
            $line->reason = null;
            $line->reasonNote = null;
            $input->lines[] = $line;
        }
        return $input;
    }

    /**
     * Re-apply the server snapshot to the security-sensitive fields
     * on every submitted line, by row index. Drops any row whose
     * index falls outside the snapshot (the row didn't exist
     * server-side so it cannot have come from a real form submission).
     */
    private function overwriteWithSnapshot(TakeInventoryInput $input, InventoryListPage $snapshot): void
    {
        $items = $snapshot->items;
        $trusted = [];
        foreach ($input->lines as $index => $line) {
            if (!isset($items[$index])) {
                continue;
            }
            $line->itemId = $items[$index]->inventoryItemId;
            $line->listingCode = $items[$index]->listingCode;
            $line->expected = $items[$index]->totalQuantityOnHand;
            $trusted[] = $line;
        }
        $input->lines = $trusted;
    }

    private function runListInventory(string $facility): InventoryListPage
    {
        // Skip the archived filter so the existing read-model code path
        // does not require explicit boolean parameter binding (the same
        // criteria signature ListInventoryController uses). Archived
        // items still surface in the grid but represent a vanishingly
        // small slice of real-world facility stock and are out of scope
        // for the LRA-87 implementation; a follow-up may add a UI
        // toggle if operators ask for it.
        $envelope = $this->queryBus->dispatch(new ListInventory(
            facilityCode: $facility,
            archived: null,
            pageSize: self::GRID_PAGE_SIZE,
        ));
        $stamp = $envelope->last(HandledStamp::class);
        if ($stamp === null) {
            throw new LogicException('ListInventory returned no HandledStamp.');
        }
        $result = $stamp->getResult();
        if (!$result instanceof InventoryListPage) {
            throw new LogicException(sprintf(
                'ListInventory returned %s, expected %s.',
                get_debug_type($result),
                InventoryListPage::class,
            ));
        }
        return $result;
    }

    /**
     * Returns true when the submit was rejected atomically (any variance
     * row missing a reason). When true, the form already carries per-row
     * field errors so the caller just needs to re-render the grid.
     *
     * @param FormInterface<TakeInventoryInput> $form
     */
    private function validateVariancesAtomically(FormInterface $form, TakeInventoryInput $input): bool
    {
        $rejected = false;
        $lines = $form->get('lines');

        foreach ($input->lines as $index => $line) {
            if (! $this->isVarianceRow($line)) {
                continue;
            }

            $reason = $line->reason;
            $resolved = $reason !== null && $reason !== '' ? StockAdjustmentReason::tryFrom($reason) : null;
            if ($resolved === null) {
                $rejected = true;
                $this->addReasonRequiredError($lines, (string) $index, (string) $line->listingCode);
            }
        }

        return $rejected;
    }

    /**
     * A row records a real variance only when both the expected and the
     * counted quantity are present and they differ. Non-variance and
     * incomplete rows are skipped by both the validation and dispatch
     * passes.
     *
     * @phpstan-assert-if-true int $line->actual
     * @phpstan-assert-if-true int $line->expected
     */
    private function isVarianceRow(TakeInventoryLineInput $line): bool
    {
        return $line->actual !== null
            && $line->expected !== null
            && $line->actual !== $line->expected;
    }

    /**
     * Attaches the "reason required" error to the reason sub-field of the
     * given variance row, when that field exists on the bound form.
     *
     * @param FormInterface<mixed> $lines
     */
    private function addReasonRequiredError(FormInterface $lines, string $index, string $listingCode): void
    {
        if (! $lines->has($index)) {
            return;
        }

        $row = $lines->get($index);
        if (! $row->has('reason')) {
            return;
        }

        $row->get('reason')->addError(new FormError(sprintf(
            self::ERR_REASON_REQUIRED_FMT,
            $listingCode,
        )));
    }

    /**
     * Dispatches one AdjustStock per variance row. Returns a map of
     * row index → human-readable error message for rows that failed.
     *
     * @return array<int, string>
     */
    private function dispatchVarianceRows(TakeInventoryInput $input, string $facility): array
    {
        $errors = [];

        foreach ($input->lines as $index => $line) {
            if (! $this->isVarianceRow($line)) {
                continue;
            }

            $error = $this->dispatchOneVariance($line, $facility);
            if ($error !== null) {
                $errors[$index] = $error;
            }
        }

        return $errors;
    }

    /**
     * Dispatches a single AdjustStock for one variance row. Returns a
     * human-readable error message when the dispatch fails, or null on
     * success.
     */
    private function dispatchOneVariance(TakeInventoryLineInput $line, string $facility): ?string
    {
        // Atomic validation above guarantees a non-empty, enum-valid
        // reason on every variance row, and isVarianceRow() guarantees a
        // counted quantity. Fall through to LogicException rather than
        // defaulting so a regression in the validation step surfaces
        // loudly instead of silently mislabelling the ledger entry.
        if ($line->reason === null || $line->reason === '') {
            throw new LogicException(
                'Atomic validation should have rejected variance rows without a reason.',
            );
        }
        if ($line->actual === null) {
            throw new LogicException(
                'isVarianceRow() should have skipped rows without a counted quantity.',
            );
        }

        $reason = $line->reason;
        $reasonNote = $line->reasonNote !== null ? trim($line->reasonNote) : '';
        $operatorReason = $reasonNote !== '' ? $reasonNote : $reason;

        try {
            $this->dispatchCommandUnwrapping(new AdjustStock(
                itemId: (string) $line->itemId,
                facilityCode: $facility,
                targetQuantityUnits: $line->actual,
                reason: $operatorReason,
                adjustmentSubReason: $reason,
            ));
        } catch (ConcurrentInventoryItemModification) {
            return sprintf(self::ERR_CONCURRENT_FMT, (string) $line->listingCode);
        } catch (Throwable) {
            return self::GENERIC_ADJUST_FAILURE;
        }

        return null;
    }

    /**
     * @param FormInterface<TakeInventoryInput> $form
     * @param array<int, string> $rowErrors
     */
    private function renderGrid(
        FormInterface $form,
        string $facility,
        array $rowErrors,
        ?string $topError,
        int $status = Response::HTTP_OK,
    ): Response {
        return $this->render(
            self::GRID_TEMPLATE,
            [
                'form' => $form->createView(),
                'facilityCode' => $facility,
                'rowErrors' => $rowErrors,
                'topError' => $topError,
                'maxReasonNoteLength' => TakeInventoryLineFormType::MAX_REASON_NOTE_LENGTH,
            ],
            new Response(null, $status),
        );
    }

    private function adjustedResponse(): Response
    {
        $response = new Response('', Response::HTTP_OK);
        $response->headers->set('HX-Trigger', 'stockAdjusted');
        $response->headers->set('HX-Reswap', 'none');
        return $response;
    }

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
