<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Persistence\Doctrine\Read;

use App\Inventory\Application\Query\GetLowStockAlerts;
use App\Inventory\Application\Query\GetStockMovementHistory;
use App\Inventory\Application\Query\ListInventory;
use App\Inventory\Application\Query\Port\InventoryReadModel;
use App\Inventory\Application\Query\View\InventoryListPage;
use App\Inventory\Application\Query\View\InventorySummaryView;
use App\Inventory\Application\Query\View\LowStockAlertView;
use App\Inventory\Application\Query\View\StockMovementPage;
use App\Inventory\Application\Query\View\StockMovementView;
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
 * All SQL is parameterised; bind values come straight from the
 * primitive criteria DTOs. Row-to-DTO coercion goes through typed
 * helpers (str/int/bool/nullableStr/nullableInt) so PHPStan stays
 * level 9 clean without inline casts.
 */
final readonly class DoctrineInventoryReadModel implements InventoryReadModel
{
    /** @var list<string> */
    private const array ALLOWED_SORT = ['name', 'code', 'quantity'];

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

        $whereClause = implode(' AND ', $where);

        $totalCount = $this->scalarToInt($this->connection->fetchOne(
            'SELECT COUNT(*) FROM inventory_items i '
            . 'JOIN catalog_listings l ON l.id = i.listing_id '
            . 'WHERE ' . $whereClause,
            $params,
        ));

        $sortColumn = $this->resolveSortColumn($criteria->sort);
        $sortDirection = str_starts_with($criteria->sort, '-') ? 'DESC' : 'ASC';

        $facilityQtySql = $criteria->facilityCode === null
            ? 'COALESCE((SELECT SUM(sb.remaining_quantity) FROM inventory_stock_batches sb WHERE sb.item_id = i.id), 0)'
            : 'COALESCE((SELECT SUM(sb.remaining_quantity) FROM inventory_stock_batches sb '
                . 'WHERE sb.item_id = i.id AND sb.facility_code = :facility), 0)';

        $rowsSql = 'SELECT '
            . 'i.id AS inventory_item_id, '
            . 'i.listing_id AS listing_id, '
            . 'l.code AS listing_code, '
            . 'l.name AS name, '
            . 'l.kind AS kind, '
            . $facilityQtySql . ' AS total_quantity, '
            . 'i.reorder_threshold_units AS reorder_threshold, '
            . 'i.archived AS archived '
            . 'FROM inventory_items i '
            . 'JOIN catalog_listings l ON l.id = i.listing_id '
            . 'WHERE ' . $whereClause . ' '
            . 'ORDER BY ' . $sortColumn . ' ' . $sortDirection . ', i.id ASC '
            . 'LIMIT :limit OFFSET :offset';

        $pageSize = max(1, $criteria->pageSize);
        $pageNumber = max(1, $criteria->pageNumber);
        $params['limit'] = $pageSize;
        $params['offset'] = ($pageNumber - 1) * $pageSize;

        $rows = $this->connection->fetchAllAssociative($rowsSql, $params);

        $itemIds = array_map(fn (array $row): string => $this->str($row, 'inventory_item_id'), $rows);
        $groupNamesByItem = $this->loadGroupNames($itemIds);

        $items = array_map(
            function (array $row) use ($groupNamesByItem): InventorySummaryView {
                $id = $this->str($row, 'inventory_item_id');
                return new InventorySummaryView(
                    inventoryItemId: $id,
                    listingId: $this->str($row, 'listing_id'),
                    listingCode: $this->str($row, 'listing_code'),
                    name: $this->str($row, 'name'),
                    kind: $this->str($row, 'kind'),
                    totalQuantityOnHand: $this->int($row, 'total_quantity'),
                    reorderThresholdUnits: $this->int($row, 'reorder_threshold'),
                    archived: $this->bool($row, 'archived'),
                    groupNames: $groupNamesByItem[$id] ?? [],
                );
            },
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
        if ($criteria->dateFrom !== null) {
            $where[] = 'sm.recorded_at >= :dateFrom';
            $params['dateFrom'] = $criteria->dateFrom->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        }
        if ($criteria->dateTo !== null) {
            $where[] = 'sm.recorded_at <= :dateTo';
            $params['dateTo'] = $criteria->dateTo->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        }

        $whereClause = implode(' AND ', $where);

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
            . 'FROM inventory_stock_movements sm '
            . 'WHERE ' . $whereClause . ' '
            . 'ORDER BY sm.recorded_at DESC, sm.id ASC '
            . 'LIMIT :limit OFFSET :offset',
            $params,
        );

        $movements = array_map(
            function (array $row): StockMovementView {
                return new StockMovementView(
                    movementId: $this->str($row, 'id'),
                    inventoryItemId: $this->str($row, 'item_id'),
                    facilityCode: $this->str($row, 'facility_code'),
                    stockBatchId: $this->nullableStr($row, 'stock_batch_id'),
                    kind: $this->str($row, 'kind'),
                    reason: $this->str($row, 'reason'),
                    quantity: $this->int($row, 'quantity'),
                    costPerUnitCents: $this->int($row, 'cost_per_unit_cents'),
                    operatorNote: $this->nullableStr($row, 'operator_note'),
                    transactionId: $this->nullableStr($row, 'transaction_id'),
                    listingId: $this->nullableStr($row, 'listing_id'),
                    recordedAt: new DateTimeImmutable($this->str($row, 'recorded_at'), new DateTimeZone('UTC')),
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

    /**
     * @return list<LowStockAlertView>
     */
    public function lowStockAlerts(GetLowStockAlerts $criteria): array
    {
        $params = [];
        $facilityClause = '';
        if ($criteria->facilityCode !== null) {
            $facilityClause = 'AND sb.facility_code = :facility ';
            $params['facility'] = $criteria->facilityCode;
        }

        $sql = 'SELECT '
            . 'i.id AS inventory_item_id, '
            . 'i.listing_id AS listing_id, '
            . 'sb.facility_code AS facility_code, '
            . 'SUM(sb.remaining_quantity) AS on_hand, '
            . 'i.reorder_threshold_units AS reorder_threshold, '
            . 'i.primary_vendor_id AS primary_vendor_id '
            . 'FROM inventory_items i '
            . 'JOIN inventory_stock_batches sb ON sb.item_id = i.id '
            . 'WHERE i.archived = false ' . $facilityClause
            . 'GROUP BY i.id, i.listing_id, sb.facility_code, '
            . 'i.reorder_threshold_units, i.primary_vendor_id '
            . 'HAVING SUM(sb.remaining_quantity) <= i.reorder_threshold_units '
            . 'ORDER BY (i.reorder_threshold_units - SUM(sb.remaining_quantity)) DESC';

        $rows = $this->connection->fetchAllAssociative($sql, $params);

        return array_map(
            function (array $row): LowStockAlertView {
                $onHand = $this->int($row, 'on_hand');
                $threshold = $this->int($row, 'reorder_threshold');
                return new LowStockAlertView(
                    inventoryItemId: $this->str($row, 'inventory_item_id'),
                    listingId: $this->str($row, 'listing_id'),
                    facilityCode: $this->str($row, 'facility_code'),
                    currentOnHandUnits: $onHand,
                    reorderThresholdUnits: $threshold,
                    shortfallUnits: $threshold - $onHand,
                    primaryVendorId: $this->nullableStr($row, 'primary_vendor_id'),
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

    private function scalarToInt(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && is_numeric($value)) {
            return (int) $value;
        }
        if (is_float($value)) {
            return (int) $value;
        }
        return 0;
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
            $byItem[$this->str($row, 'item_id')][] = $this->str($row, 'name');
        }

        return $byItem;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function str(array $row, string $key): string
    {
        $value = $row[$key] ?? null;
        if (is_string($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        return '';
    }

    /**
     * @param array<string, mixed> $row
     */
    private function nullableStr(array $row, string $key): ?string
    {
        $value = $row[$key] ?? null;
        if ($value === null) {
            return null;
        }
        if (is_string($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }
        return null;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function int(array $row, string $key): int
    {
        $value = $row[$key] ?? null;
        if (is_int($value)) {
            return $value;
        }
        if (is_float($value)) {
            return (int) $value;
        }
        if (is_string($value) && is_numeric($value)) {
            return (int) $value;
        }
        if (is_bool($value)) {
            return $value ? 1 : 0;
        }
        return 0;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function bool(array $row, string $key): bool
    {
        $value = $row[$key] ?? null;
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value)) {
            return $value !== 0;
        }
        if (is_string($value)) {
            $lower = strtolower($value);
            return $value !== '' && $value !== '0' && $lower !== 'f' && $lower !== 'false';
        }
        return false;
    }
}
