<?php

declare(strict_types=1);

namespace App\Inventory\Application\Command;

final readonly class ArchiveItemGroup
{
    public function __construct(public string $itemGroupId)
    {
    }
}
