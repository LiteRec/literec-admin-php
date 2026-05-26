<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Event;

use App\Inventory\Domain\ValueObject\ItemLinkId;
use DateTimeImmutable;

final readonly class ItemUnlinked
{
    public function __construct(
        public ItemLinkId $itemLinkId,
        public DateTimeImmutable $occurredAt,
    ) {
    }
}
