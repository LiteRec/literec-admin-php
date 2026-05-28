<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Http\Controller;

use App\Inventory\Application\Query\GetLowStockAlerts;
use App\Inventory\Application\Query\ListInventory;
use App\Inventory\Application\Query\Port\InventoryReadModel;
use App\Inventory\Application\Query\Report\CurrentStockReport;
use App\Inventory\Application\Query\Report\CurrentStockReportHandler;
use App\Inventory\Application\Query\Report\CurrentStockReportPage;
use App\Inventory\Application\Query\Report\EntryLogPage;
use App\Inventory\Application\Query\Report\EntryLogReport;
use App\Inventory\Application\Query\Report\EntryLogReportHandler;
use App\Inventory\Application\Query\View\InventoryListPage;
use App\Inventory\Application\Query\View\InventorySummaryView;
use App\Inventory\Application\Query\View\LowStockAlertView;
use App\Inventory\Domain\Barcode\BarcodeRenderer;
use App\Inventory\Domain\ValueObject\StockMovementKind;
use App\Inventory\Domain\ValueObject\StockMovementReason;
use App\Inventory\Infrastructure\Http\Csv\CsvStreamer;
use DateTimeImmutable;
use DateTimeZone;
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
 * LRA-91 — Inventory Reports dashboard.
 *
 * Renders the dashboard shell, four HTMX-driven report cards (Current
 * Stock, Entry Log, Low Stock Alerts, Barcode Batch Print), and the
 * CSV-export endpoints. Two additional report tiles (Sales Breakdown,
 * Demographic Breakdown) ship as inert "Coming soon" placeholders.
 *
 * Permission gating: every action requires the `view_inventory`
 * permission via {@see \App\Inventory\Infrastructure\Security\InventoryVoter}.
 *
 * CSV export rationale: the two streaming exports inject the report
 * handlers directly (rather than dispatching through the query bus)
 * because routing a `\Generator` through Messenger envelopes would
 * buffer the entire export behind a single handler stamp and defeat
 * the streaming contract.
 */
final class InventoryReportsController extends AbstractController
{
    use HandleTrait {
        handle as private dispatchQuery;
    }

    private const string PERMISSION_VIEW = 'view_inventory';

    private const string UUID_V7_REQUIREMENT = '[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}';

    private const string TEMPLATE_INDEX = 'inventory/reports/index.html.twig';

    private const string TEMPLATE_CURRENT_STOCK_CARD = 'inventory/reports/_card_current_stock_body.html.twig';

    private const string TEMPLATE_ENTRY_LOG_CARD = 'inventory/reports/_card_entry_log_body.html.twig';

    private const string TEMPLATE_LOW_STOCK_CARD = 'inventory/reports/_card_low_stock_body.html.twig';

    private const string TEMPLATE_BARCODE_FORM = 'inventory/reports/barcodes/_form.html.twig';

    private const string TEMPLATE_BARCODE_PRINT = 'inventory/reports/barcodes/_print.html.twig';

    private const string CSV_STEM_CURRENT_STOCK = 'current-stock';

    private const string CSV_STEM_ENTRY_LOG = 'entry-log';

    private const string CSV_STEM_LOW_STOCK = 'low-stock';

    /** Cap on barcode batch print size to avoid generating arbitrarily large pages. */
    private const int MAX_BARCODE_ITEMS = 500;

    /** Page size for the barcode picker grid. */
    private const int BARCODE_PICKER_PAGE_SIZE = 200;

    private const int DEFAULT_ENTRY_LOG_PAGE_SIZE = 50;

    private const int MAX_PAGE_SIZE = 200;

    private const string CURRENT_STOCK_HEADER_CODE = 'Code';

    private const string CURRENT_STOCK_HEADER_NAME = 'Name';

    private const string CURRENT_STOCK_HEADER_KIND = 'Kind';

    private const string CURRENT_STOCK_HEADER_FACILITY = 'Facility';

    private const string CURRENT_STOCK_HEADER_ON_HAND = 'OnHand';

    private const string CURRENT_STOCK_HEADER_THRESHOLD = 'ReorderThreshold';

    private const string CURRENT_STOCK_HEADER_BELOW = 'AtOrBelowThreshold';

    private const string ENTRY_LOG_HEADER_DATE = 'Date';

    private const string ENTRY_LOG_HEADER_CODE = 'Code';

    private const string ENTRY_LOG_HEADER_ITEM_ID = 'ItemId';

    private const string ENTRY_LOG_HEADER_FACILITY = 'Facility';

    private const string ENTRY_LOG_HEADER_KIND = 'Kind';

    private const string ENTRY_LOG_HEADER_REASON = 'Reason';

    private const string ENTRY_LOG_HEADER_QUANTITY = 'Quantity';

    private const string ENTRY_LOG_HEADER_COST_PER_UNIT = 'CostPerUnit';

    private const string ENTRY_LOG_HEADER_OPERATOR_NOTE = 'OperatorNote';

    private const string LOW_STOCK_HEADER_ITEM_ID = 'ItemId';

    private const string LOW_STOCK_HEADER_LISTING_ID = 'ListingId';

    private const string LOW_STOCK_HEADER_FACILITY = 'Facility';

    private const string LOW_STOCK_HEADER_ON_HAND = 'OnHand';

    private const string LOW_STOCK_HEADER_THRESHOLD = 'ReorderThreshold';

    private const string LOW_STOCK_HEADER_SHORTFALL = 'Shortfall';

    private const string LOW_STOCK_HEADER_VENDOR_ID = 'PrimaryVendorId';

    public function __construct(
        MessageBusInterface $queryBus,
        private readonly CsvStreamer $csvStreamer,
        private readonly BarcodeRenderer $barcodeRenderer,
        private readonly CurrentStockReportHandler $currentStockHandler,
        private readonly EntryLogReportHandler $entryLogHandler,
        private readonly InventoryReadModel $inventoryReadModel,
    ) {
        $this->messageBus = $queryBus;
    }

    #[Route('/admin/inventory/reports', name: 'inventory_reports_index', methods: ['GET'])]
    #[IsGranted(self::PERMISSION_VIEW)]
    public function index(Request $request): Response
    {
        $currentStock = $this->loadCurrentStock($this->buildCurrentStockCriteria($request));
        $entryLog = $this->loadEntryLog($this->buildEntryLogCriteria($request));
        $lowStock = $this->loadLowStock($this->buildLowStockCriteria($request));

        return $this->render(self::TEMPLATE_INDEX, [
            'currentStock' => $currentStock,
            'entryLog' => $entryLog,
            'lowStock' => $lowStock,
            'kindCases' => StockMovementKind::cases(),
            'reasonCases' => StockMovementReason::cases(),
        ]);
    }

    #[Route(
        '/admin/inventory/reports/current-stock/_card',
        name: 'inventory_reports_current_stock_card',
        methods: ['GET'],
    )]
    #[IsGranted(self::PERMISSION_VIEW)]
    public function currentStockCard(Request $request): Response
    {
        $criteria = $this->buildCurrentStockCriteria($request);
        $page = $this->loadCurrentStock($criteria);

        return $this->render(self::TEMPLATE_CURRENT_STOCK_CARD, [
            'currentStock' => $page,
            'criteria' => $criteria,
        ]);
    }

    #[Route(
        '/admin/inventory/reports/entry-log/_card',
        name: 'inventory_reports_entry_log_card',
        methods: ['GET'],
    )]
    #[IsGranted(self::PERMISSION_VIEW)]
    public function entryLogCard(Request $request): Response
    {
        $criteria = $this->buildEntryLogCriteria($request);
        $page = $this->loadEntryLog($criteria);

        return $this->render(self::TEMPLATE_ENTRY_LOG_CARD, [
            'entryLog' => $page,
            'criteria' => $criteria,
            'kindCases' => StockMovementKind::cases(),
            'reasonCases' => StockMovementReason::cases(),
        ]);
    }

    #[Route(
        '/admin/inventory/reports/low-stock/_card',
        name: 'inventory_reports_low_stock_card',
        methods: ['GET'],
    )]
    #[IsGranted(self::PERMISSION_VIEW)]
    public function lowStockCard(Request $request): Response
    {
        $criteria = $this->buildLowStockCriteria($request);
        $alerts = $this->loadLowStock($criteria);

        return $this->render(self::TEMPLATE_LOW_STOCK_CARD, [
            'lowStock' => $alerts,
            'criteria' => $criteria,
        ]);
    }

    #[Route(
        '/admin/inventory/reports/current-stock/export.csv',
        name: 'inventory_reports_current_stock_export',
        methods: ['GET'],
    )]
    #[IsGranted(self::PERMISSION_VIEW)]
    public function exportCurrentStock(Request $request): StreamedResponse
    {
        $criteria = $this->buildCurrentStockCriteria($request);

        return $this->csvStreamer->streamingResponse(
            rows: $this->currentStockHandler->streamCsvRows($criteria),
            header: [
                self::CURRENT_STOCK_HEADER_CODE,
                self::CURRENT_STOCK_HEADER_NAME,
                self::CURRENT_STOCK_HEADER_KIND,
                self::CURRENT_STOCK_HEADER_FACILITY,
                self::CURRENT_STOCK_HEADER_ON_HAND,
                self::CURRENT_STOCK_HEADER_THRESHOLD,
                self::CURRENT_STOCK_HEADER_BELOW,
            ],
            filenameStem: self::CSV_STEM_CURRENT_STOCK,
        );
    }

    #[Route(
        '/admin/inventory/reports/entry-log/export.csv',
        name: 'inventory_reports_entry_log_export',
        methods: ['GET'],
    )]
    #[IsGranted(self::PERMISSION_VIEW)]
    public function exportEntryLog(Request $request): StreamedResponse
    {
        $criteria = $this->buildEntryLogCriteria($request);

        return $this->csvStreamer->streamingResponse(
            rows: $this->entryLogHandler->streamCsvRows($criteria),
            header: [
                self::ENTRY_LOG_HEADER_DATE,
                self::ENTRY_LOG_HEADER_CODE,
                self::ENTRY_LOG_HEADER_ITEM_ID,
                self::ENTRY_LOG_HEADER_FACILITY,
                self::ENTRY_LOG_HEADER_KIND,
                self::ENTRY_LOG_HEADER_REASON,
                self::ENTRY_LOG_HEADER_QUANTITY,
                self::ENTRY_LOG_HEADER_COST_PER_UNIT,
                self::ENTRY_LOG_HEADER_OPERATOR_NOTE,
            ],
            filenameStem: self::CSV_STEM_ENTRY_LOG,
        );
    }

    #[Route(
        '/admin/inventory/reports/low-stock/export.csv',
        name: 'inventory_reports_low_stock_export',
        methods: ['GET'],
    )]
    #[IsGranted(self::PERMISSION_VIEW)]
    public function exportLowStock(Request $request): StreamedResponse
    {
        // Low Stock alerts are intentionally bounded (one row per
        // item × facility that is at or below threshold) and the
        // underlying read-model port returns the full filtered list in
        // memory by design — see GetLowStockAlerts /
        // lowStockAlerts(). Streaming buys nothing here because the
        // result set is already small; the helper still wraps the
        // pre-built array in the same CSV response shape as the
        // other exports so the front-end download flow is uniform.
        $criteria = $this->buildLowStockCriteria($request);
        $alerts = $this->loadLowStock($criteria);

        $rows = self::lowStockToCsvRows($alerts);

        return $this->csvStreamer->streamingResponse(
            rows: $rows,
            header: [
                self::LOW_STOCK_HEADER_ITEM_ID,
                self::LOW_STOCK_HEADER_LISTING_ID,
                self::LOW_STOCK_HEADER_FACILITY,
                self::LOW_STOCK_HEADER_ON_HAND,
                self::LOW_STOCK_HEADER_THRESHOLD,
                self::LOW_STOCK_HEADER_SHORTFALL,
                self::LOW_STOCK_HEADER_VENDOR_ID,
            ],
            filenameStem: self::CSV_STEM_LOW_STOCK,
        );
    }

    #[Route(
        '/admin/inventory/reports/barcodes',
        name: 'inventory_reports_barcode_form',
        methods: ['GET'],
    )]
    #[IsGranted(self::PERMISSION_VIEW)]
    public function barcodeForm(Request $request): Response
    {
        $picker = $this->loadInventoryListing($request);

        return $this->render(self::TEMPLATE_BARCODE_FORM, [
            'picker' => $picker,
            'maxItems' => self::MAX_BARCODE_ITEMS,
        ]);
    }

    #[Route(
        '/admin/inventory/reports/barcodes/print',
        name: 'inventory_reports_barcode_print',
        methods: ['POST'],
    )]
    #[IsGranted(self::PERMISSION_VIEW)]
    public function barcodePrint(Request $request): Response
    {
        // The endpoint is read-only — it renders a print sheet of
        // selected items and writes no state. Permission gating via
        // `view_inventory` (the IsGranted attribute above) is the
        // security boundary; CSRF protection adds no value because
        // there is nothing for a forged cross-site POST to mutate.
        $rawIds = $request->request->all('itemIds');
        $itemIds = self::filterValidItemIds($rawIds);

        if (count($itemIds) > self::MAX_BARCODE_ITEMS) {
            $itemIds = array_slice($itemIds, 0, self::MAX_BARCODE_ITEMS);
        }

        // Resolve the selected ids through the read-model's bulk-by-id
        // port so the SQL filter happens server-side (a paginated
        // ListInventory walk would silently drop selections that fell
        // on the wrong side of any page boundary). Items whose id is
        // not found are dropped — the print sheet shows labels for
        // what still exists in inventory.
        $listing = $this->inventoryReadModel->findByIds($itemIds);

        $labels = array_map(
            function (InventorySummaryView $item): array {
                return [
                    'inventoryItemId' => $item->inventoryItemId,
                    'listingCode' => $item->listingCode,
                    'name' => $item->name,
                    'barcodeHtml' => $this->barcodeRenderer->renderHtml($item->listingCode),
                ];
            },
            $listing,
        );

        return $this->render(self::TEMPLATE_BARCODE_PRINT, [
            'labels' => $labels,
        ]);
    }

    private function loadCurrentStock(CurrentStockReport $criteria): CurrentStockReportPage
    {
        $result = $this->dispatchQuery($criteria);
        if (! $result instanceof CurrentStockReportPage) {
            throw new LogicException(sprintf(
                'CurrentStockReport handler returned %s, expected %s.',
                get_debug_type($result),
                CurrentStockReportPage::class,
            ));
        }
        return $result;
    }

    private function loadEntryLog(EntryLogReport $criteria): EntryLogPage
    {
        $result = $this->dispatchQuery($criteria);
        if (! $result instanceof EntryLogPage) {
            throw new LogicException(sprintf(
                'EntryLogReport handler returned %s, expected %s.',
                get_debug_type($result),
                EntryLogPage::class,
            ));
        }
        return $result;
    }

    /**
     * @return list<LowStockAlertView>
     */
    private function loadLowStock(GetLowStockAlerts $criteria): array
    {
        $result = $this->dispatchQuery($criteria);
        if (! is_array($result)) {
            throw new LogicException(sprintf(
                'GetLowStockAlerts handler returned %s, expected array.',
                get_debug_type($result),
            ));
        }
        // Element-type narrowing: the handler contract returns
        // list<LowStockAlertView>, but HandleTrait::handle()'s return is
        // declared `mixed`, so PHPStan needs the filter to recover the
        // element type without an inline @var (forbidden per project
        // rules).
        return array_values(array_filter(
            $result,
            static fn (mixed $row): bool => $row instanceof LowStockAlertView,
        ));
    }

    private function loadInventoryListing(Request $request): InventoryListPage
    {
        // archived: null means "no archived filter applied". The existing
        // Doctrine read model binds the boolean param without an explicit
        // PDO type, so passing `false` crashes on Postgres ("invalid input
        // syntax for type boolean: ''"). Picker UX is unaffected — the
        // barcode print sheet shows the same set the operator sees on the
        // primary list page.
        $query = new ListInventory(
            search: self::stringOrEmpty($request->query->get('search')),
            facilityCode: self::stringOrNull($request->query->get('facilityCode')),
            groupId: self::stringOrNull($request->query->get('groupId')),
            kind: self::stringOrNull($request->query->get('kind')),
            archived: null,
            sort: 'name',
            pageNumber: 1,
            pageSize: self::BARCODE_PICKER_PAGE_SIZE,
        );

        $result = $this->dispatchQuery($query);
        if (! $result instanceof InventoryListPage) {
            throw new LogicException(sprintf(
                'ListInventory handler returned %s, expected %s.',
                get_debug_type($result),
                InventoryListPage::class,
            ));
        }
        return $result;
    }

    private function buildCurrentStockCriteria(Request $request): CurrentStockReport
    {
        $query = $request->query;
        return new CurrentStockReport(
            facilityCode: self::stringOrNull($query->get('facilityCode')),
            groupId: self::stringOrNull($query->get('groupId')),
            kindFilter: self::stringOrNull($query->get('kind')),
        );
    }

    private function buildEntryLogCriteria(Request $request): EntryLogReport
    {
        $query = $request->query;
        $kindRaw = self::stringOrNull($query->get('kind'));
        $kind = $kindRaw !== null ? StockMovementKind::tryFrom($kindRaw)?->value : null;

        $reasonRaw = self::stringOrNull($query->get('reason'));
        $reason = $reasonRaw !== null ? StockMovementReason::tryFrom($reasonRaw)?->value : null;

        $pageNumber = max(1, $query->getInt('page', 1));
        $pageSize = $query->getInt('pageSize', self::DEFAULT_ENTRY_LOG_PAGE_SIZE);
        if ($pageSize < 1) {
            $pageSize = 1;
        }
        if ($pageSize > self::MAX_PAGE_SIZE) {
            $pageSize = self::MAX_PAGE_SIZE;
        }

        return new EntryLogReport(
            dateFrom: self::parseDate(self::stringOrNull($query->get('dateFrom')), startOfDay: true),
            dateTo: self::parseDate(self::stringOrNull($query->get('dateTo')), startOfDay: false),
            facilityCode: self::stringOrNull($query->get('facilityCode')),
            kind: $kind,
            reason: $reason,
            pageNumber: $pageNumber,
            pageSize: $pageSize,
        );
    }

    private function buildLowStockCriteria(Request $request): GetLowStockAlerts
    {
        return new GetLowStockAlerts(
            facilityCode: self::stringOrNull($request->query->get('facilityCode')),
        );
    }

    /**
     * @param list<LowStockAlertView> $alerts
     * @return list<list<string>>
     */
    private static function lowStockToCsvRows(array $alerts): array
    {
        return array_map(
            static fn (LowStockAlertView $alert): array => [
                $alert->inventoryItemId,
                $alert->listingId,
                $alert->facilityCode,
                (string) $alert->currentOnHandUnits,
                (string) $alert->reorderThresholdUnits,
                (string) $alert->shortfallUnits,
                $alert->primaryVendorId ?? '',
            ],
            $alerts,
        );
    }

    /**
     * @param array<int|string, mixed> $raw
     * @return list<string>
     */
    private static function filterValidItemIds(array $raw): array
    {
        $valid = [];
        $pattern = '/^' . self::UUID_V7_REQUIREMENT . '$/i';
        foreach ($raw as $candidate) {
            if (is_string($candidate) && preg_match($pattern, $candidate) === 1) {
                $valid[] = strtolower($candidate);
            }
        }
        return array_values(array_unique($valid));
    }

    private static function stringOrNull(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }
        $trimmed = trim($value);
        return $trimmed === '' ? null : $trimmed;
    }

    private static function stringOrEmpty(mixed $value): string
    {
        if (! is_string($value)) {
            return '';
        }
        return trim($value);
    }

    /**
     * Parses a YYYY-MM-DD operator input into a UTC `DateTimeImmutable`.
     * Returns null on any parse failure so a malformed filter just
     * disables itself rather than 400-ing the page.
     */
    private static function parseDate(?string $raw, bool $startOfDay): ?DateTimeImmutable
    {
        if ($raw === null) {
            return null;
        }
        $parsed = DateTimeImmutable::createFromFormat(
            'Y-m-d',
            $raw,
            new DateTimeZone('UTC'),
        );
        if ($parsed === false) {
            return null;
        }
        if ($parsed->format('Y-m-d') !== $raw) {
            return null;
        }
        return $startOfDay
            ? $parsed->setTime(0, 0, 0)
            : $parsed->setTime(23, 59, 59);
    }
}
