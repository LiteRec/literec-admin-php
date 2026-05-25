<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Event;

use App\Catalog\Domain\ValueObject\ListingId;
use App\Catalog\Domain\ValueObject\TaxTreatment;
use DateTimeImmutable;

final readonly class ListingTaxTreatmentUpdated
{
    public function __construct(
        public ListingId $listingId,
        public TaxTreatment $taxTreatment,
        public DateTimeImmutable $occurredAt,
    ) {
    }
}
