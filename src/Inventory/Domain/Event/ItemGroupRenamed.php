<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Event;

use App\Inventory\Domain\ValueObject\ItemGroupId;
use App\Inventory\Domain\ValueObject\ItemGroupName;
use DateTimeImmutable;

final readonly class ItemGroupRenamed
{
    public function __construct(
        public ItemGroupId $itemGroupId,
        public ItemGroupName $name,
        public DateTimeImmutable $occurredAt,
    ) {
    }
}
