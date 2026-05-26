<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Event;

use App\Inventory\Domain\ValueObject\ItemGroupId;
use App\Inventory\Domain\ValueObject\PosColor;
use DateTimeImmutable;

final readonly class ItemGroupRecolored
{
    public function __construct(
        public ItemGroupId $itemGroupId,
        public PosColor $color,
        public DateTimeImmutable $occurredAt,
    ) {
    }
}
