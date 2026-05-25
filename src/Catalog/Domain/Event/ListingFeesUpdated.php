<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Event;

use App\Catalog\Domain\ValueObject\Fee;
use App\Catalog\Domain\ValueObject\ListingId;
use DateTimeImmutable;

final readonly class ListingFeesUpdated
{
    /**
     * @param list<Fee> $fees
     */
    public function __construct(
        public ListingId $listingId,
        public array $fees,
        public DateTimeImmutable $occurredAt,
    ) {
    }
}
