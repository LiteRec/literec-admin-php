<?php

declare(strict_types=1);

namespace App\Inventory\Application\Query;

use App\Inventory\Application\Query\Port\InventoryReadModel;
use App\Inventory\Application\Query\View\StockMovementPage;
use Generator;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class GetStockMovementHistoryHandler
{
    public function __construct(private InventoryReadModel $readModel)
    {
    }

    public function __invoke(GetStockMovementHistory $query): StockMovementPage
    {
        return $this->readModel->stockMovements($query);
    }

    /**
     * Streams the full filtered set as CSV rows for the LRA-88 export.
     * Delegates to the port so the controller stays free of
     * persistence concerns; the paginated `__invoke()` and this
     * streaming entry point share the same WHERE-clause semantics
     * inside the port implementation.
     *
     * @return Generator<int, list<string>, mixed, void>
     */
    public function streamCsvRows(GetStockMovementHistory $criteria): Generator
    {
        return $this->readModel->streamStockMovements($criteria);
    }
}
