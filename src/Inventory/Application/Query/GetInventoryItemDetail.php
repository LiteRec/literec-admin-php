<?php

declare(strict_types=1);

namespace App\Inventory\Application\Query;

final readonly class GetInventoryItemDetail
{
    public function __construct(
        public string $inventoryItemId,
    ) {
    }
}
