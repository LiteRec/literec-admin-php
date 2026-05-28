<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Persistence\Doctrine\Read;

use App\Inventory\Application\Query\GetLowStockAlerts;
use App\Inventory\Application\Query\GetStockMovementHistory;
use App\Inventory\Application\Query\ListInventory;
use App\Inventory\Application\Query\Port\InventoryReadModel;
use App\Inventory\Application\Query\Report\CurrentStockReport;
use App\Inventory\Application\Query\Report\CurrentStockReportPage;
use App\Inventory\Application\Query\Report\CurrentStockRowView;
use App\Inventory\Application\Query\Report\EntryLogPage;
use App\Inventory\Application\Query\Report\EntryLogReport;
use App\Inventory\Application\Query\Report\EntryLogRowView;
use App\Inventory\Application\Query\View\InventoryListPage;
use App\Inventory\Application\Query\View\InventorySummaryView;
use App\Inventory\Application\Query\View\LowStockAlertView;
use App\Inventory\Application\Query\View\StockMovementPage;
use App\Inventory\Application\Query\View\StockMovementView;
use App\Shared\Infrastructure\Doctrine\Read\RowFieldExtraction;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;

/**
 * DBAL-backed read model (LRA-97). Projects directly from the OLTP
 * tables without going through the EntityManager — no UnitOfWork, no
 * aggregate hydration, no N+1.
 *
 * Read paths supported:
 *   - ListInventory → inventory_items joined with stock_batches
 *     (summed per item) plus listing details from catalog_listings
 *     and group names from inventory_item_group_members.
 *   - GetStockMovementHistory → inventory_stock_movements via the
 *     (item_id, facility_code, recorded_at DESC) index.
 *   - GetLowStockAlerts → inventory_items × stock_batches with a
 *     per-facility HAVING SUM(...) <= reorder_threshold filter.
 *
 * Row-to-DTO coercion goes through the shared RowFieldExtraction
 * trait so PHPStan stays level 9 clean without inline (string)/(int)
 * casts and SonarCloud's CPD does not flag the helpers as a
 * cross-context duplicate of DoctrineMemberReadModel.
 */
final readonly class DoctrineInventoryReadModel implements InventoryReadModel
{
    use RowFieldExtraction;

    /** @var list<string> */
    private const array ALLOWED_SORT = ['name', 'code', 'quantity'];

    /**
     * Page size used by {@see streamStockMovements()} to walk the
     * filtered ledger in bounded chunks. Tuned for one full chunk
     * (≈ 500 rows) to fit comfortably in DBAL's per-statement buffer
     * while still amortising the per-statement round-trip cost across
     * many rows. Independent of the operator-facing pageSize on
     * {@see GetStockMovementHistory}.
     */
    private const int STREAM_CHUNK_SIZE = 500;

    /**
     * Reused SQL fragments + format strings. Extracted to satisfy SonarCloud
     * php:S1192 (no string literal duplicated 4+ times) and to keep the
     * projection queries assembled from a single source of truth.
     */
    private const string SQL_SELECT = 'SELECT ';

    private const string SQL_WHERE = 'WHERE ';

    private const string SQL_AND = ' AND ';

    private const string FROM_ITEMS = 'FROM inventory_items i ';

    private const string FROM_MOVEMENTS = 'FROM inventory_stock_movements sm ';

    private const string JOIN_LISTINGS = 'JOIN catalog_listings l ON l.id = i.listing_id ';

    private const string JOIN_ITEMS_ON_MOVEMENT = 'JOIN inventory_items i ON i.id = sm.item_id ';

    private const string ORDER_MOVEMENTS = 'ORDER BY sm.recorded_at DESC, sm.id ASC ';

    private const string LIMIT_OFFSET = 'LIMIT :limit OFFSET :offset';

    private const string COL_ITEM_ID = 'i.id AS inventory_item_id, ';

    private const string COL_LISTING_ID = 'i.listing_id AS listing_id, ';

    private const string COL_LISTING_CODE = 'l.code AS listing_code, ';

    private const string COL_NAME = 'l.name AS name, ';

    private const string COL_KIND = 'l.kind AS kind, ';

    private const string COL_REORDER_THRESHOLD = 'i.reorder_threshold AS reorder_threshold, ';

    private const string COALESCE_BATCH_QTY
        = 'COALESCE((SELECT SUM(sb.remaining_quantity) FROM inventory_stock_batches sb ';

    private const string UTC_TIMESTAMP_FORMAT = 'Y-m-d H:i:s';

    public function __construct(private Connection $connection)
    {
    }

    public function list(ListInventory $criteria): InventoryListPage
    {
        $where = ['1 = 1'];
        $params = [];

        if ($criteria->search !== '') {
            $where[] = '(LOWER(l.code) LIKE LOWER(:search) OR LOWER(l.name) LIKE LOWER(:search))';
            $params['search'] = '%' . $criteria->search . '%';
        }
        if ($criteria->kind !== null) {
            $where[] = 'l.kind = :kind';
            $params['kind'] = $criteria->kind;
        }
        if ($criteria->archived !== null) {
            $where[] = 'i.archived = :archived';
            $params['archived'] = $criteria->archived;
        }
        if ($criteria->facilityCode !== null) {
            $where[] = 'EXISTS (SELECT 1 FROM inventory_stock_batches sb '
                . 'WHERE sb.item_id = i.id AND sb.facility_code = :facility)';
            $params['facility'] = $criteria->facilityCode;
        }
        if ($criteria->groupId !== null) {
            $where[] = 'EXISTS (SELECT 1 FROM inventory_item_group_members m '
                . 'WHERE m.item_id = i.id AND m.group_id = :groupId)';
            $params['groupId'] = $criteria->groupId;
        }

        $whereClause = implode(self::SQL_AND, $where);

        $totalCount = $this->scalarToInt($this->connection->fetchOne(
            'SELECT COUNT(*) FROM inventory_items i '
            . self::JOIN_LISTINGS
            . self::SQL_WHERE . $whereClause,
            $params,
        ));

        $sortColumn = $this->resolveSortColumn($criteria->sort);
        $sortDirection = str_starts_with($criteria->sort, '-') ? 'DESC' : 'ASC';

        $facilityQtySql = $criteria->facilityCode === null
            ? 'COALESCE((SELECT SUM(sb.remaining_quantity) FROM inventory_stock_batches sb WHERE sb.item_id = i.id), 0)'
            : self::COALESCE_BATCH_QTY
                . 'WHERE sb.item_id = i.id AND sb.facility_code = :facility), 0)';

        $rowsSql = self::SQL_SELECT
            . self::COL_ITEM_ID
            . self::COL_LISTING_ID
            . self::COL_LISTING_CODE
            . self::COL_NAME
            . self::COL_KIND
            . $facilityQtySql . ' AS total_quantity, '
            . self::COL_REORDER_THRESHOLD
            . 'i.archived AS archived '
            . self::FROM_ITEMS
            . self::JOIN_LISTINGS
            . self::SQL_WHERE . $whereClause . ' '
            . 'ORDER BY ' . $sortColumn . ' ' . $sortDirection . ', i.id ASC '
            . self::LIMIT_OFFSET;

        $pageSize = max(1, $criteria->pageSize);
        $pageNumber = max(1, $criteria->pageNumber);
        $params['limit'] = $pageSize;
        $params['offset'] = ($pageNumber - 1) * $pageSize;

        $rows = $this->connection->fetchAllAssociative($rowsSql, $params);

        $itemIds = array_map(fn (array $row): string => $this->rowString($row, 'inventory_item_id'), $rows);
        $groupNamesByItem = $this->loadGroupNames($itemIds);

        $items = array_map(
            fn (array $row): InventorySummaryView => $this->mapSummaryRow($row, $groupNamesByItem),
            $rows,
        );

        return new InventoryListPage(
            items: $items,
            totalCount: $totalCount,
            pageNumber: $pageNumber,
            pageSize: $pageSize,
        );
    }

    public function stockMovements(GetStockMovementHistory $criteria): StockMovementPage
    {
        [$whereClause, $params] = $this->buildMovementFilter($criteria);

        $totalCount = $this->scalarToInt($this->connection->fetchOne(
            'SELECT COUNT(*) FROM inventory_stock_movements sm WHERE ' . $whereClause,
            $params,
        ));

        $pageSize = max(1, $criteria->pageSize);
        $pageNumber = max(1, $criteria->pageNumber);
        $params['limit'] = $pageSize;
        $params['offset'] = ($pageNumber - 1) * $pageSize;

        $rows = $this->connection->fetchAllAssociative(
            'SELECT sm.id, sm.item_id, sm.facility_code, sm.stock_batch_id, sm.kind, sm.reason, '
            . 'sm.quantity, sm.cost_per_unit_cents, sm.operator_note, sm.transaction_id, sm.listing_id, sm.recorded_at '
            . self::FROM_MOVEMENTS
            . self::SQL_WHERE . $whereClause . ' '
            . self::ORDER_MOVEMENTS
            . self::LIMIT_OFFSET,
            $params,
        );

        $movements = array_map(
            function (array $row): StockMovementView {
                return new StockMovementView(
                    movementId: $this->rowString($row, 'id'),
                    inventoryItemId: $this->rowString($row, 'item_id'),
                    facilityCode: $this->rowString($row, 'facility_code'),
                    stockBatchId: $this->rowNullableString($row, 'stock_batch_id'),
                    kind: $this->rowString($row, 'kind'),
                    reason: $this->rowString($row, 'reason'),
                    quantity: $this->rowInt($row, 'quantity'),
                    costPerUnitCents: $this->rowInt($row, 'cost_per_unit_cents'),
                    operatorNote: $this->rowNullableString($row, 'operator_note'),
                    transactionId: $this->rowNullableString($row, 'transaction_id'),
                    listingId: $this->rowNullableString($row, 'listing_id'),
                    recordedAt: new DateTimeImmutable($this->rowString($row, 'recorded_at'), new DateTimeZone('UTC')),
                );
            },
            $rows,
        );

        return new StockMovementPage(
            movements: $movements,
            totalCount: $totalCount,
            pageNumber: $pageNumber,
            pageSize: $pageSize,
        );
    }

    public function streamStockMovements(GetStockMovementHistory $criteria): \Generator
    {
        [$whereClause, $params] = $this->buildMovementFilter($criteria);

        // Offset pagination on the same filter SQL — server-side cursors
        // are not portable across drivers, but a tight LIMIT/OFFSET walk
        // with an immutable WHERE clause stays well within memory while
        // letting Postgres reuse the (item_id, facility_code, recorded_at
        // DESC) index for each chunk.
        $offset = 0;
        $chunkSize = self::STREAM_CHUNK_SIZE;
        $params['limit'] = $chunkSize;

        $sql = 'SELECT sm.recorded_at, sm.kind, sm.reason, sm.facility_code, sm.quantity, '
            . 'sb.received_at AS batch_received_at, sm.cost_per_unit_cents, sm.operator_note, sm.transaction_id '
            . self::FROM_MOVEMENTS
            . 'LEFT JOIN inventory_stock_batches sb ON sb.id = sm.stock_batch_id '
            . self::SQL_WHERE . $whereClause . ' '
            . self::ORDER_MOVEMENTS
            . self::LIMIT_OFFSET;

        do {
            $params['offset'] = $offset;
            $rows = $this->connection->fetchAllAssociative($sql, $params);

            foreach ($rows as $row) {
                $batchReceived = $this->rowNullableString($row, 'batch_received_at');
                $batchReceivedIso = $batchReceived !== null
                    ? (new DateTimeImmutable($batchReceived, new DateTimeZone('UTC')))->format(DateTimeImmutable::ATOM)
                    : '';

                yield [
                    (new DateTimeImmutable(
                        $this->rowString($row, 'recorded_at'),
                        new DateTimeZone('UTC'),
                    ))->format(DateTimeImmutable::ATOM),
                    $this->rowString($row, 'kind'),
                    $this->rowString($row, 'reason'),
                    $this->rowString($row, 'facility_code'),
                    (string) $this->rowInt($row, 'quantity'),
                    $batchReceivedIso,
                    (string) $this->rowInt($row, 'cost_per_unit_cents'),
                    $this->rowNullableString($row, 'operator_note') ?? '',
                    $this->rowNullableString($row, 'transaction_id') ?? '',
                ];
            }

            $offset += $chunkSize;
        } while (count($rows) === $chunkSize);
    }

    /**
     * Builds the WHERE clause shared by {@see stockMovements()} and
     * {@see streamStockMovements()} so the paginated read and the
     * unpaginated export apply identical filter semantics.
     *
     * @return array{0: string, 1: array<string, scalar>}
     */
    private function buildMovementFilter(GetStockMovementHistory $criteria): array
    {
        $where = ['1 = 1'];
        $params = [];

        if ($criteria->inventoryItemId !== null) {
            $where[] = 'sm.item_id = :itemId';
            $params['itemId'] = $criteria->inventoryItemId;
        }
        if ($criteria->facilityCode !== null) {
            $where[] = 'sm.facility_code = :facility';
            $params['facility'] = $criteria->facilityCode;
        }
        if ($criteria->kind !== null) {
            $where[] = 'sm.kind = :kind';
            $params['kind'] = $criteria->kind;
        }
        if ($criteria->reason !== null) {
            $where[] = 'sm.reason = :reason';
            $params['reason'] = $criteria->reason;
        }
        $this->appendRecordedAtRange($where, $params, $criteria->dateFrom, $criteria->dateTo);

        return [implode(self::SQL_AND, $where), $params];
    }

    /**
     * Appends the inclusive recorded_at lower/upper bounds shared by the
     * stock-movement and entry-log filters, normalising both to UTC.
     *
     * @param list<string>          $where
     * @param array<string, scalar> $params
     */
    private function appendRecordedAtRange(
        array &$where,
        array &$params,
        ?DateTimeImmutable $dateFrom,
        ?DateTimeImmutable $dateTo,
    ): void {
        if ($dateFrom !== null) {
            $where[] = 'sm.recorded_at >= :dateFrom';
            $params['dateFrom'] = $dateFrom
                ->setTimezone(new DateTimeZone('UTC'))
                ->format(self::UTC_TIMESTAMP_FORMAT);
        }
        if ($dateTo !== null) {
            $where[] = 'sm.recorded_at <= :dateTo';
            $params['dateTo'] = $dateTo
                ->setTimezone(new DateTimeZone('UTC'))
                ->format(self::UTC_TIMESTAMP_FORMAT);
        }
    }

    /**
     * @return list<LowStockAlertView>
     */
    public function lowStockAlerts(GetLowStockAlerts $criteria): array
    {
        $params = [];

        if ($criteria->facilityCode !== null) {
            // Facility-scoped: LEFT JOIN with the facility filter on
            // the join predicate so items with zero on-hand at the
            // requested facility (no batch rows at all) still show as
            // alerts at on_hand = 0. The literal :facility on the
            // SELECT side guarantees every alert row carries the
            // requested facility code even for items with no rows in
            // inventory_stock_batches.
            $params['facility'] = $criteria->facilityCode;
            $sql = self::SQL_SELECT
                . self::COL_ITEM_ID
                . self::COL_LISTING_ID
                . ':facility AS facility_code, '
                . 'COALESCE(SUM(sb.remaining_quantity), 0) AS on_hand, '
                . self::COL_REORDER_THRESHOLD
                . 'i.primary_vendor_id AS primary_vendor_id '
                . self::FROM_ITEMS
                . 'LEFT JOIN inventory_stock_batches sb '
                . 'ON sb.item_id = i.id AND sb.facility_code = :facility '
                . 'WHERE i.archived = false '
                . 'GROUP BY i.id, i.listing_id, '
                . 'i.reorder_threshold, i.primary_vendor_id '
                . 'HAVING COALESCE(SUM(sb.remaining_quantity), 0) <= i.reorder_threshold '
                . 'ORDER BY (i.reorder_threshold - COALESCE(SUM(sb.remaining_quantity), 0)) DESC';
        } else {
            // All-facilities scan: an item appears once per facility
            // it has stock at, and only when that facility's sum is
            // at or below the per-item threshold. INNER JOIN is
            // correct here — without a facility filter we cannot
            // synthesise a facility_code for items that have no
            // batches anywhere, so they are excluded from the report.
            $sql = self::SQL_SELECT
                . self::COL_ITEM_ID
                . self::COL_LISTING_ID
                . 'sb.facility_code AS facility_code, '
                . 'SUM(sb.remaining_quantity) AS on_hand, '
                . self::COL_REORDER_THRESHOLD
                . 'i.primary_vendor_id AS primary_vendor_id '
                . self::FROM_ITEMS
                . 'JOIN inventory_stock_batches sb ON sb.item_id = i.id '
                . 'WHERE i.archived = false '
                . 'GROUP BY i.id, i.listing_id, sb.facility_code, '
                . 'i.reorder_threshold, i.primary_vendor_id '
                . 'HAVING SUM(sb.remaining_quantity) <= i.reorder_threshold '
                . 'ORDER BY (i.reorder_threshold - SUM(sb.remaining_quantity)) DESC';
        }

        $rows = $this->connection->fetchAllAssociative($sql, $params);

        return array_map(
            function (array $row): LowStockAlertView {
                $onHand = $this->rowInt($row, 'on_hand');
                $threshold = $this->rowInt($row, 'reorder_threshold');
                return new LowStockAlertView(
                    inventoryItemId: $this->rowString($row, 'inventory_item_id'),
                    listingId: $this->rowString($row, 'listing_id'),
                    facilityCode: $this->rowString($row, 'facility_code'),
                    currentOnHandUnits: $onHand,
                    reorderThresholdUnits: $threshold,
                    shortfallUnits: $threshold - $onHand,
                    primaryVendorId: $this->rowNullableString($row, 'primary_vendor_id'),
                );
            },
            $rows,
        );
    }

    private function resolveSortColumn(string $sort): string
    {
        $base = ltrim($sort, '-');
        if (! in_array($base, self::ALLOWED_SORT, true)) {
            $base = 'name';
        }

        return match ($base) {
            'code' => 'l.code',
            'quantity' => 'total_quantity',
            default => 'l.name',
        };
    }

    /**
     * Intentionally unpaginated: the LRA-91 Current Stock card shows
     * the full filtered set (mirrors {@see lowStockAlerts()}). Bounded
     * in practice by the operator's filter selection (facility, kind,
     * group). The CSV export uses {@see streamCurrentStock()} for
     * unbounded result sets.
     */
    public function currentStock(CurrentStockReport $criteria): CurrentStockReportPage
    {
        [$whereClause, $params, $facilityQtySql, $facilityCodeExpr] = $this->buildCurrentStockSelect($criteria);

        $sql = self::SQL_SELECT
            . self::COL_ITEM_ID
            . self::COL_LISTING_CODE
            . self::COL_NAME
            . self::COL_KIND
            . $facilityCodeExpr . ' AS facility_code, '
            . $facilityQtySql . ' AS on_hand, '
            . 'i.reorder_threshold AS reorder_threshold '
            . self::FROM_ITEMS
            . self::JOIN_LISTINGS
            . self::SQL_WHERE . $whereClause . ' '
            . 'ORDER BY l.name ASC, i.id ASC';

        $rows = $this->connection->fetchAllAssociative($sql, $params);

        $items = array_map(
            function (array $row): CurrentStockRowView {
                $onHand = $this->rowInt($row, 'on_hand');
                $threshold = $this->rowInt($row, 'reorder_threshold');
                return new CurrentStockRowView(
                    inventoryItemId: $this->rowString($row, 'inventory_item_id'),
                    listingCode: $this->rowString($row, 'listing_code'),
                    name: $this->rowString($row, 'name'),
                    kind: $this->rowString($row, 'kind'),
                    facilityCode: $this->rowString($row, 'facility_code'),
                    onHandUnits: $onHand,
                    reorderThresholdUnits: $threshold,
                    isAtOrBelowThreshold: $threshold > 0 && $onHand <= $threshold,
                );
            },
            $rows,
        );

        return new CurrentStockReportPage(items: $items, totalCount: count($items));
    }

    public function streamCurrentStock(CurrentStockReport $criteria): \Generator
    {
        [$whereClause, $params, $facilityQtySql, $facilityCodeExpr] = $this->buildCurrentStockSelect($criteria);

        $offset = 0;
        $chunkSize = self::STREAM_CHUNK_SIZE;
        $params['limit'] = $chunkSize;

        $sql = self::SQL_SELECT
            . self::COL_LISTING_CODE
            . self::COL_NAME
            . self::COL_KIND
            . $facilityCodeExpr . ' AS facility_code, '
            . $facilityQtySql . ' AS on_hand, '
            . 'i.reorder_threshold AS reorder_threshold '
            . self::FROM_ITEMS
            . self::JOIN_LISTINGS
            . self::SQL_WHERE . $whereClause . ' '
            . 'ORDER BY l.name ASC, i.id ASC '
            . self::LIMIT_OFFSET;

        do {
            $params['offset'] = $offset;
            $rows = $this->connection->fetchAllAssociative($sql, $params);

            foreach ($rows as $row) {
                $onHand = $this->rowInt($row, 'on_hand');
                $threshold = $this->rowInt($row, 'reorder_threshold');
                $isBelow = $threshold > 0 && $onHand <= $threshold;

                yield [
                    $this->rowString($row, 'listing_code'),
                    $this->rowString($row, 'name'),
                    $this->rowString($row, 'kind'),
                    $this->rowString($row, 'facility_code'),
                    (string) $onHand,
                    (string) $threshold,
                    $isBelow ? '1' : '0',
                ];
            }

            $offset += $chunkSize;
        } while (count($rows) === $chunkSize);
    }

    public function entryLog(EntryLogReport $criteria): EntryLogPage
    {
        [$whereClause, $params] = $this->buildEntryLogFilter($criteria);

        $totalCount = $this->scalarToInt($this->connection->fetchOne(
            'SELECT COUNT(*) FROM inventory_stock_movements sm '
            . self::JOIN_ITEMS_ON_MOVEMENT
            . self::JOIN_LISTINGS
            . self::SQL_WHERE . $whereClause,
            $params,
        ));

        $pageSize = max(1, $criteria->pageSize);
        $pageNumber = max(1, $criteria->pageNumber);
        $params['limit'] = $pageSize;
        $params['offset'] = ($pageNumber - 1) * $pageSize;

        $rows = $this->connection->fetchAllAssociative(
            'SELECT sm.id, sm.item_id, l.code AS listing_code, sm.facility_code, '
            . 'sm.kind, sm.reason, sm.quantity, sm.cost_per_unit_cents, '
            . 'sm.operator_note, sm.recorded_at '
            . self::FROM_MOVEMENTS
            . self::JOIN_ITEMS_ON_MOVEMENT
            . self::JOIN_LISTINGS
            . self::SQL_WHERE . $whereClause . ' '
            . self::ORDER_MOVEMENTS
            . self::LIMIT_OFFSET,
            $params,
        );

        $entries = array_map(
            function (array $row): EntryLogRowView {
                return new EntryLogRowView(
                    movementId: $this->rowString($row, 'id'),
                    inventoryItemId: $this->rowString($row, 'item_id'),
                    listingCode: $this->rowString($row, 'listing_code'),
                    facilityCode: $this->rowString($row, 'facility_code'),
                    kind: $this->rowString($row, 'kind'),
                    reason: $this->rowString($row, 'reason'),
                    quantity: $this->rowInt($row, 'quantity'),
                    costPerUnitCents: $this->rowInt($row, 'cost_per_unit_cents'),
                    operatorNote: $this->rowNullableString($row, 'operator_note'),
                    recordedAt: new DateTimeImmutable(
                        $this->rowString($row, 'recorded_at'),
                        new DateTimeZone('UTC'),
                    ),
                );
            },
            $rows,
        );

        return new EntryLogPage(
            rows: $entries,
            totalCount: $totalCount,
            pageNumber: $pageNumber,
            pageSize: $pageSize,
        );
    }

    public function streamEntryLog(EntryLogReport $criteria): \Generator
    {
        [$whereClause, $params] = $this->buildEntryLogFilter($criteria);

        $offset = 0;
        $chunkSize = self::STREAM_CHUNK_SIZE;
        $params['limit'] = $chunkSize;

        $sql = 'SELECT sm.recorded_at, sm.kind, sm.reason, sm.facility_code, sm.quantity, '
            . 'sm.cost_per_unit_cents, sm.operator_note, l.code AS listing_code, sm.item_id '
            . self::FROM_MOVEMENTS
            . self::JOIN_ITEMS_ON_MOVEMENT
            . self::JOIN_LISTINGS
            . self::SQL_WHERE . $whereClause . ' '
            . self::ORDER_MOVEMENTS
            . self::LIMIT_OFFSET;

        do {
            $params['offset'] = $offset;
            $rows = $this->connection->fetchAllAssociative($sql, $params);

            foreach ($rows as $row) {
                yield [
                    (new DateTimeImmutable(
                        $this->rowString($row, 'recorded_at'),
                        new DateTimeZone('UTC'),
                    ))->format(DateTimeImmutable::ATOM),
                    $this->rowString($row, 'listing_code'),
                    $this->rowString($row, 'item_id'),
                    $this->rowString($row, 'facility_code'),
                    $this->rowString($row, 'kind'),
                    $this->rowString($row, 'reason'),
                    (string) $this->rowInt($row, 'quantity'),
                    (string) $this->rowInt($row, 'cost_per_unit_cents'),
                    $this->rowNullableString($row, 'operator_note') ?? '',
                ];
            }

            $offset += $chunkSize;
        } while (count($rows) === $chunkSize);
    }

    /**
     * Builds the WHERE clause + facility-aware SELECT fragments shared
     * by {@see currentStock()} and {@see streamCurrentStock()}.
     *
     * @return array{0: string, 1: array<string, scalar>, 2: string, 3: string}
     */
    private function buildCurrentStockSelect(CurrentStockReport $criteria): array
    {
        // Mirror lowStockAlerts() and the LRA-85 list-page default:
        // archived items never appear in the operator-facing reports
        // (they exist for historical auditing only).
        $where = ['i.archived = false'];
        $params = [];

        if ($criteria->kindFilter !== null && $criteria->kindFilter !== '') {
            $where[] = 'l.kind = :kindFilter';
            $params['kindFilter'] = $criteria->kindFilter;
        }
        if ($criteria->groupId !== null && $criteria->groupId !== '') {
            $where[] = 'EXISTS (SELECT 1 FROM inventory_item_group_members m '
                . 'WHERE m.item_id = i.id AND m.group_id = :groupId)';
            $params['groupId'] = $criteria->groupId;
        }

        if ($criteria->facilityCode !== null && $criteria->facilityCode !== '') {
            $params['facility'] = $criteria->facilityCode;
            $where[] = 'EXISTS (SELECT 1 FROM inventory_stock_batches sb '
                . 'WHERE sb.item_id = i.id AND sb.facility_code = :facility)';
            $facilityQtySql = self::COALESCE_BATCH_QTY
                . 'WHERE sb.item_id = i.id AND sb.facility_code = :facility), 0)';
            $facilityCodeExpr = ':facility';
        } else {
            $facilityQtySql = self::COALESCE_BATCH_QTY
                . 'WHERE sb.item_id = i.id), 0)';
            // No facility filter: surface a synthetic ALL marker so the
            // CurrentStockRowView always carries a non-empty facility
            // code without joining the per-facility breakdown.
            $facilityCodeExpr = "'ALL'";
        }

        return [implode(self::SQL_AND, $where), $params, $facilityQtySql, $facilityCodeExpr];
    }

    /**
     * Shared WHERE-clause builder for {@see entryLog()} and
     * {@see streamEntryLog()}.
     *
     * @return array{0: string, 1: array<string, scalar>}
     */
    private function buildEntryLogFilter(EntryLogReport $criteria): array
    {
        // Reports never surface ledger rows for archived items; the
        // Entry Log query joins inventory_items so we can apply the
        // same archived filter the Current Stock and Low Stock
        // projections use.
        $where = ['i.archived = false'];
        $params = [];

        if ($criteria->facilityCode !== null && $criteria->facilityCode !== '') {
            $where[] = 'sm.facility_code = :facility';
            $params['facility'] = $criteria->facilityCode;
        }
        if ($criteria->kind !== null && $criteria->kind !== '') {
            $where[] = 'sm.kind = :kind';
            $params['kind'] = $criteria->kind;
        }
        if ($criteria->reason !== null && $criteria->reason !== '') {
            $where[] = 'sm.reason = :reason';
            $params['reason'] = $criteria->reason;
        }
        $this->appendRecordedAtRange($where, $params, $criteria->dateFrom, $criteria->dateTo);

        return [implode(self::SQL_AND, $where), $params];
    }

    public function findByIds(array $inventoryItemIds): array
    {
        if ($inventoryItemIds === []) {
            return [];
        }

        $rows = $this->connection->fetchAllAssociative(
            self::SQL_SELECT
            . self::COL_ITEM_ID
            . self::COL_LISTING_ID
            . self::COL_LISTING_CODE
            . self::COL_NAME
            . self::COL_KIND
            . self::COALESCE_BATCH_QTY
            . 'WHERE sb.item_id = i.id), 0) AS total_quantity, '
            . self::COL_REORDER_THRESHOLD
            . 'i.archived AS archived '
            . self::FROM_ITEMS
            . self::JOIN_LISTINGS
            . 'WHERE i.id IN (:ids) '
            . 'ORDER BY l.name ASC, i.id ASC',
            ['ids' => $inventoryItemIds],
            ['ids' => ArrayParameterType::STRING],
        );

        $itemIds = array_map(fn (array $row): string => $this->rowString($row, 'inventory_item_id'), $rows);
        $groupNamesByItem = $this->loadGroupNames($itemIds);

        return array_map(
            fn (array $row): InventorySummaryView => $this->mapSummaryRow($row, $groupNamesByItem),
            $rows,
        );
    }

    /**
     * Maps one inventory_items × catalog_listings row (with summed
     * on-hand) to the list/detail summary view. Shared by {@see list()}
     * and {@see findByIds()}.
     *
     * @param array<string, mixed>          $row
     * @param array<string, list<string>>   $groupNamesByItem
     */
    private function mapSummaryRow(array $row, array $groupNamesByItem): InventorySummaryView
    {
        $id = $this->rowString($row, 'inventory_item_id');

        return new InventorySummaryView(
            inventoryItemId: $id,
            listingId: $this->rowString($row, 'listing_id'),
            listingCode: $this->rowString($row, 'listing_code'),
            name: $this->rowString($row, 'name'),
            kind: $this->rowString($row, 'kind'),
            totalQuantityOnHand: $this->rowInt($row, 'total_quantity'),
            reorderThresholdUnits: $this->rowInt($row, 'reorder_threshold'),
            archived: $this->rowBool($row, 'archived'),
            groupNames: $groupNamesByItem[$id] ?? [],
        );
    }

    /**
     * @param list<string> $itemIds
     * @return array<string, list<string>>
     */
    private function loadGroupNames(array $itemIds): array
    {
        if ($itemIds === []) {
            return [];
        }

        $rows = $this->connection->fetchAllAssociative(
            'SELECT m.item_id, g.name FROM inventory_item_group_members m '
            . 'JOIN inventory_item_groups g ON g.id = m.group_id '
            . 'WHERE m.item_id IN (:itemIds) AND g.archived = false '
            . 'ORDER BY g.name ASC',
            ['itemIds' => $itemIds],
            ['itemIds' => ArrayParameterType::STRING],
        );

        $byItem = [];
        foreach ($rows as $row) {
            $byItem[$this->rowString($row, 'item_id')][] = $this->rowString($row, 'name');
        }

        return $byItem;
    }
}
