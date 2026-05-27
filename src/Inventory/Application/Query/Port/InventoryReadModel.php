<?php

declare(strict_types=1);

namespace App\Inventory\Application\Query\Port;

use App\Inventory\Application\Query\GetLowStockAlerts;
use App\Inventory\Application\Query\GetStockMovementHistory;
use App\Inventory\Application\Query\ListInventory;
use App\Inventory\Application\Query\View\InventoryListPage;
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
}
