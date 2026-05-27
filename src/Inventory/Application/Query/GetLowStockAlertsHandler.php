<?php

declare(strict_types=1);

namespace App\Inventory\Application\Query;

use App\Inventory\Application\Query\Port\InventoryReadModel;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class GetLowStockAlertsHandler
{
    public function __construct(private InventoryReadModel $readModel)
    {
    }

    /**
     * @return list<\App\Inventory\Application\Query\View\LowStockAlertView>
     */
    public function __invoke(GetLowStockAlerts $query): array
    {
        return $this->readModel->lowStockAlerts($query);
    }
}
