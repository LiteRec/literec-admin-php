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
     * @return list<LowStockAlertView>
     */
    public function lowStockAlerts(GetLowStockAlerts $criteria): array;
}
