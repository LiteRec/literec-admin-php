<?php

declare(strict_types=1);

namespace App\Catalog\Application\Command;

/**
 * Primitive-only command DTO for the RegisterListing use case.
 *
 * Value-object construction happens inside the handler so invalid input
 * surfaces as a named domain exception rather than a constructor
 * TypeError at the bus boundary.
 *
 * @phpstan-type FeeInput array{amountCents: int, currency: string, label: string}
 */
final readonly class RegisterListing
{
    /**
     * @param list<FeeInput> $fees
     */
    public function __construct(
        public string $code,
        public string $kind,
        public string $name,
        public array $fees,
        public bool $taxApply,
        public bool $taxIncludedInFee,
        public string $ledgerAccount,
    ) {
    }
}
