<?php

declare(strict_types=1);

namespace App\Catalog\Application\Command;

final readonly class UpdateListingTaxTreatment
{
    public function __construct(
        public string $listingId,
        public bool $taxApply,
        public bool $taxIncludedInFee,
    ) {
    }
}
