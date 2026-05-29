<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Http\Controller;

use App\Inventory\Application\Query\GetInventoryItemDetail;
use App\Inventory\Application\Query\GetStockMovementHistory;
use App\Inventory\Application\Query\GetStockMovementHistoryHandler;
use App\Inventory\Application\Query\View\InventoryItemDetailView;
use App\Inventory\Application\Query\View\StockMovementPage;
use App\Inventory\Domain\Exception\InventoryItemNotFound;
use App\Inventory\Domain\ValueObject\StockMovementKind;
use App\Inventory\Domain\ValueObject\StockMovementReason;
use App\Inventory\Infrastructure\Http\Csv\CsvStreamer;
use LogicException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * HTTP adapter for the LRA-88 Stock Movement History page + FIFO batch
 * panel + CSV export.
 *
 * Four routes share the same query layer:
 *   - `inventory_item_history`            renders the full page shell.
 *   - `inventory_item_history_movements`  HTMX partial: just the table.
 *   - `inventory_item_history_batches`    HTMX partial: FIFO panel.
 *   - `inventory_item_history_export`     CSV stream of the filtered set.
 *
 * The controller dispatches the paginated read through the query bus
 * (`GetStockMovementHistory`) but holds a direct reference to the
 * {@see GetStockMovementHistoryHandler} for the CSV export — the bus
 * round-trip would buffer the entire generator behind a single
 * envelope/handler stamp, defeating the streaming contract. The handler
 * is registered as a service via `#[AsMessageHandler]`, so injecting it
 * directly is the standard Symfony idiom for sidestepping the bus.
 */
final class StockMovementHistoryController extends AbstractController
{
    use HandleTrait {
        handle as private dispatchQuery;
    }
    use RequestQueryParsing;

    private const string UUID_V7_REQUIREMENT = '[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}';

    private const string ITEM_NOT_FOUND = 'Inventory item not found.';

    private const string PERMISSION_VIEW = 'view_inventory';

    private const string TEMPLATE_INDEX = 'inventory/history/index.html.twig';

    private const string TEMPLATE_MOVEMENTS = 'inventory/history/_movements.html.twig';

    private const string TEMPLATE_BATCHES = 'inventory/history/_batches.html.twig';

    private const string CSV_HEADER_DATE = 'Date';

    private const string CSV_HEADER_KIND = 'Kind';

    private const string CSV_HEADER_REASON = 'Reason';

    private const string CSV_HEADER_FACILITY = 'Facility';

    private const string CSV_HEADER_QUANTITY = 'Quantity';

    private const string CSV_HEADER_BATCH_RECEIVED = 'BatchReceived';

    private const string CSV_HEADER_COST_PER_UNIT = 'CostPerUnit';

    private const string CSV_HEADER_OPERATOR_NOTE = 'OperatorNote';

    private const string CSV_HEADER_TRANSACTION_ID = 'TransactionId';

    private const int DEFAULT_PAGE_SIZE = 50;

    private const int MAX_PAGE_SIZE = 200;

    private const string CSV_FILENAME_STEM_PREFIX = 'history-';

    private const int ITEM_ID_SHORT_LENGTH = 8;

    public function __construct(
        MessageBusInterface $queryBus,
        private readonly GetStockMovementHistoryHandler $historyHandler,
        private readonly CsvStreamer $csvStreamer,
    ) {
        $this->messageBus = $queryBus;
    }

    #[Route(
        '/admin/inventory/{itemId}/history',
        name: 'inventory_item_history',
        requirements: ['itemId' => self::UUID_V7_REQUIREMENT],
        methods: ['GET'],
    )]
    #[IsGranted(self::PERMISSION_VIEW)]
    public function index(string $itemId, Request $request): Response
    {
        try {
            $detail = $this->loadDetail($itemId);
        } catch (InventoryItemNotFound) {
            throw $this->createNotFoundException(self::ITEM_NOT_FOUND);
        }

        $criteria = $this->buildCriteria($request, $itemId);
        $page = $this->runMovementsQuery($criteria);

        return $this->render(self::TEMPLATE_INDEX, [
            'page' => $page,
            'detail' => $detail,
            'criteria' => $criteria,
            'itemId' => $itemId,
            'kindCases' => StockMovementKind::cases(),
            'reasonCases' => StockMovementReason::cases(),
        ]);
    }

    #[Route(
        '/admin/inventory/{itemId}/history/_movements',
        name: 'inventory_item_history_movements',
        requirements: ['itemId' => self::UUID_V7_REQUIREMENT],
        methods: ['GET'],
    )]
    #[IsGranted(self::PERMISSION_VIEW)]
    public function movements(string $itemId, Request $request): Response
    {
        $criteria = $this->buildCriteria($request, $itemId);
        $page = $this->runMovementsQuery($criteria);

        return $this->render(self::TEMPLATE_MOVEMENTS, [
            'page' => $page,
            'criteria' => $criteria,
            'itemId' => $itemId,
        ]);
    }

    #[Route(
        '/admin/inventory/{itemId}/history/_batches',
        name: 'inventory_item_history_batches',
        requirements: ['itemId' => self::UUID_V7_REQUIREMENT],
        methods: ['GET'],
    )]
    #[IsGranted(self::PERMISSION_VIEW)]
    public function batches(string $itemId): Response
    {
        try {
            $detail = $this->loadDetail($itemId);
        } catch (InventoryItemNotFound) {
            throw $this->createNotFoundException(self::ITEM_NOT_FOUND);
        }

        return $this->render(self::TEMPLATE_BATCHES, [
            'detail' => $detail,
            'itemId' => $itemId,
        ]);
    }

    #[Route(
        '/admin/inventory/{itemId}/history.csv',
        name: 'inventory_item_history_export',
        requirements: ['itemId' => self::UUID_V7_REQUIREMENT],
        methods: ['GET'],
    )]
    #[IsGranted(self::PERMISSION_VIEW)]
    public function export(string $itemId, Request $request): StreamedResponse
    {
        $criteria = $this->buildCriteria($request, $itemId);

        $filenameStem = self::CSV_FILENAME_STEM_PREFIX . substr($itemId, 0, self::ITEM_ID_SHORT_LENGTH);

        return $this->csvStreamer->streamingResponse(
            rows: $this->historyHandler->streamCsvRows($criteria),
            header: [
                self::CSV_HEADER_DATE,
                self::CSV_HEADER_KIND,
                self::CSV_HEADER_REASON,
                self::CSV_HEADER_FACILITY,
                self::CSV_HEADER_QUANTITY,
                self::CSV_HEADER_BATCH_RECEIVED,
                self::CSV_HEADER_COST_PER_UNIT,
                self::CSV_HEADER_OPERATOR_NOTE,
                self::CSV_HEADER_TRANSACTION_ID,
            ],
            filenameStem: $filenameStem,
        );
    }

    private function loadDetail(string $itemId): InventoryItemDetailView
    {
        $result = $this->dispatchQuery(new GetInventoryItemDetail($itemId));
        if (! $result instanceof InventoryItemDetailView) {
            throw new LogicException(sprintf(
                'GetInventoryItemDetail handler returned %s, expected %s.',
                get_debug_type($result),
                InventoryItemDetailView::class,
            ));
        }
        return $result;
    }

    private function runMovementsQuery(GetStockMovementHistory $criteria): StockMovementPage
    {
        $result = $this->dispatchQuery($criteria);
        if (! $result instanceof StockMovementPage) {
            throw new LogicException(sprintf(
                'GetStockMovementHistory handler returned %s, expected %s.',
                get_debug_type($result),
                StockMovementPage::class,
            ));
        }
        return $result;
    }

    private function buildCriteria(Request $request, string $itemId): GetStockMovementHistory
    {
        $query = $request->query;

        $dateFrom = self::parseDate(self::stringOrNull($query->get('dateFrom')), startOfDay: true);
        $dateTo = self::parseDate(self::stringOrNull($query->get('dateTo')), startOfDay: false);

        $kindRaw = self::stringOrNull($query->get('kind'));
        $kind = $kindRaw !== null ? StockMovementKind::tryFrom($kindRaw)?->value : null;

        $reasonRaw = self::stringOrNull($query->get('reason'));
        $reason = $reasonRaw !== null ? StockMovementReason::tryFrom($reasonRaw)?->value : null;

        $pageNumber = max(1, $query->getInt('page', 1));
        $pageSize = $query->getInt('pageSize', self::DEFAULT_PAGE_SIZE);
        if ($pageSize < 1) {
            $pageSize = 1;
        }
        if ($pageSize > self::MAX_PAGE_SIZE) {
            $pageSize = self::MAX_PAGE_SIZE;
        }

        return new GetStockMovementHistory(
            inventoryItemId: $itemId,
            facilityCode: self::stringOrNull($query->get('facilityCode')),
            dateFrom: $dateFrom,
            dateTo: $dateTo,
            kind: $kind,
            reason: $reason,
            pageNumber: $pageNumber,
            pageSize: $pageSize,
        );
    }
}
