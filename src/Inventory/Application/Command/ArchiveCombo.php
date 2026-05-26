<?php

declare(strict_types=1);

namespace App\Inventory\Application\Command;

final readonly class ArchiveCombo
{
    public function __construct(public string $comboId)
    {
    }
}
