<?php

declare(strict_types=1);

namespace App\Inventory\Application\Query;

use App\Inventory\Application\Query\Port\InventoryReadModel;
use App\Inventory\Application\Query\View\StockMovementPage;
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
}
