<?php

declare(strict_types=1);

namespace App\Catalog\Application\Command;

/**
 * @phpstan-type FeeInput array{amountCents: int, currency: string, label: string}
 */
final readonly class UpdateListingFees
{
    /**
     * @param list<FeeInput> $fees
     */
    public function __construct(
        public string $listingId,
        public array $fees,
    ) {
    }
}
