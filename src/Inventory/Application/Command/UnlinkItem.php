<?php

declare(strict_types=1);

namespace App\Inventory\Application\Command;

final readonly class UnlinkItem
{
    public function __construct(public string $itemLinkId)
    {
    }
}
