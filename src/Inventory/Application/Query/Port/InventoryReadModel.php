<?php

declare(strict_types=1);

namespace App\Inventory\Application\Query\Port;

use App\Inventory\Application\Query\GetLowStockAlerts;
use App\Inventory\Application\Query\GetStockMovementHistory;
use App\Inventory\Application\Query\ListInventory;
use App\Inventory\Application\Query\Report\CurrentStockReport;
use App\Inventory\Application\Query\Report\CurrentStockReportPage;
use App\Inventory\Application\Query\Report\EntryLogPage;
use App\Inventory\Application\Query\Report\EntryLogReport;
use App\Inventory\Application\Query\View\InventoryListPage;
use App\Inventory\Application\Query\View\InventorySummaryView;
use App\Inventory\Application\Query\View\LowStockAlertView;
use App\Inventory\Application\Query\View\StockMovementPage;

/**
 * Read-side port for the LRA-97 query handlers (consumed by LRA-85
 * list page, LRA-88 movement history, LRA-91 reports).
 *
 * Each method maps one-to-one with a handler. Implementations may
 * project from the OLTP tables directly (Doctrine adapter) or from
 * an in-memory fixture (test adapter); the contract is "given a
 * criteria DTO, return the matching view DTO". No aggregate or
 * entity instances leak through.
 */
interface InventoryReadModel
{
    public function list(ListInventory $criteria): InventoryListPage;

    public function stockMovements(GetStockMovementHistory $criteria): StockMovementPage;

    /**
     * Streams the full filtered movement set (no pagination) as CSV
     * rows for the LRA-88 export. The yielded value per iteration is
     * a `list<string>` of pre-formatted scalar fields (no header row;
     * the controller emits the header itself).
     *
     * Implementations MUST iterate the underlying result set in bounded
     * chunks so the export does not buffer the entire ledger into
     * memory. The {@see GetStockMovementHistory::$pageSize} on the
     * criteria DTO is ignored — streaming uses its own internal chunk
     * size suited to server-side cursors.
     *
     * @return \Generator<int, list<string>, mixed, void>
     */
    public function streamStockMovements(GetStockMovementHistory $criteria): \Generator;

    /**
     * @return list<LowStockAlertView>
     */
    public function lowStockAlerts(GetLowStockAlerts $criteria): array;

    /**
     * LRA-91 Current Stock card: a flat projection of every inventory
     * item (filtered by the supplied criteria) with its current on-hand
     * total. The same data drives the CSV export via
     * {@see streamCurrentStock()}.
     */
    public function currentStock(CurrentStockReport $criteria): CurrentStockReportPage;

    /**
     * LRA-91 Entry Log card: paginated projection of the stock movement
     * ledger across every item (in contrast to
     * {@see stockMovements()}, which is scoped to one item).
     */
    public function entryLog(EntryLogReport $criteria): EntryLogPage;

    /**
     * Streaming companion to {@see currentStock()} for the CSV export.
     * Yields a `list<string>` per row of pre-formatted scalar fields
     * (no header row; the controller emits the header itself).
     * Implementations MUST walk the filtered result set in bounded
     * chunks so the export does not buffer everything into memory.
     *
     * @return \Generator<int, list<string>, mixed, void>
     */
    public function streamCurrentStock(CurrentStockReport $criteria): \Generator;

    /**
     * Streaming companion to {@see entryLog()} for the CSV export.
     * Yields a `list<string>` per row of pre-formatted scalar fields
     * (no header row; the controller emits the header itself), mirroring
     * the contract of {@see streamCurrentStock()}: implementations MUST
     * walk the filtered ledger in bounded chunks so the export does not
     * buffer the entire result set into memory. The
     * {@see EntryLogReport::$pageSize} and {@see EntryLogReport::$pageNumber}
     * fields are ignored — streaming uses its own internal chunk size.
     *
     * @return \Generator<int, list<string>, mixed, void>
     */
    public function streamEntryLog(EntryLogReport $criteria): \Generator;

    /**
     * LRA-91 barcode batch print: looks up a known set of inventory
     * items by their primary keys. Used by the print sheet to render
     * one label per selected item. Implementations MUST issue a single
     * IN (...) query — paginated reads through {@see list()} are
     * unsafe here because the operator's selection can fall on either
     * side of any page boundary.
     *
     * Items whose id is not found are silently dropped (matches the
     * UX of "render labels for what's still in inventory"). The
     * returned list preserves the natural sort of {@see list()}
     * (listing name ascending).
     *
     * @param list<string> $inventoryItemIds
     * @return list<InventorySummaryView>
     */
    public function findByIds(array $inventoryItemIds): array;
}
