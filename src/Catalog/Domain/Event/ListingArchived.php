<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Event;

use App\Catalog\Domain\ValueObject\ListingId;
use DateTimeImmutable;

final readonly class ListingArchived
{
    public function __construct(
        public ListingId $listingId,
        public DateTimeImmutable $occurredAt,
    ) {
    }
}
