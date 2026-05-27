<?php

declare(strict_types=1);

namespace App\Inventory\Application\Query;

use App\Inventory\Application\Query\Port\InventoryReadModel;
use App\Inventory\Application\Query\View\InventoryListPage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class ListInventoryHandler
{
    public function __construct(private InventoryReadModel $readModel)
    {
    }

    public function __invoke(ListInventory $query): InventoryListPage
    {
        return $this->readModel->list($query);
    }
}
