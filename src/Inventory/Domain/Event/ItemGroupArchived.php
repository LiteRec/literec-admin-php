<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Event;

use App\Inventory\Domain\ValueObject\ItemGroupId;
use DateTimeImmutable;

final readonly class ItemGroupArchived
{
    public function __construct(
        public ItemGroupId $itemGroupId,
        public DateTimeImmutable $occurredAt,
    ) {
    }
}
