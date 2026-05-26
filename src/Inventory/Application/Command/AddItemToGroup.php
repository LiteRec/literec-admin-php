<?php

declare(strict_types=1);

namespace App\Inventory\Application\Command;

final readonly class AddItemToGroup
{
    public function __construct(
        public string $itemGroupId,
        public string $inventoryItemId,
    ) {
    }
}
