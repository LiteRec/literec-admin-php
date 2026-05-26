<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Event;

use App\Catalog\Domain\ValueObject\ListingId;
use App\Inventory\Domain\ValueObject\ComboComponent;
use App\Inventory\Domain\ValueObject\ComboId;
use DateTimeImmutable;

final readonly class ComboDefined
{
    /**
     * @param list<ComboComponent> $components
     */
    public function __construct(
        public ComboId $comboId,
        public ListingId $listingId,
        public array $components,
        public DateTimeImmutable $occurredAt,
    ) {
    }
}
