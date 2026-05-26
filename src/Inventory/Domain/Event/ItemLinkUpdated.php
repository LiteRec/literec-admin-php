<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Event;

use App\Inventory\Domain\ValueObject\ItemLinkId;
use App\Inventory\Domain\ValueObject\Quantity;
use DateTimeImmutable;

final readonly class ItemLinkUpdated
{
    public function __construct(
        public ItemLinkId $itemLinkId,
        public Quantity $reservedQuantity,
        public bool $unlimited,
        public Quantity $minRequired,
        public Quantity $maxPerPurchase,
        public ?DateTimeImmutable $includeUntil,
        public DateTimeImmutable $occurredAt,
    ) {
    }
}
