<?php

declare(strict_types=1);

namespace App\Catalog\Domain\ValueObject;

use App\Catalog\Domain\Exception\InvalidTaxTreatment;

/**
 * How tax is computed and presented for a Listing.
 *
 * Invariant: when {@see $applyTax} is false the fee cannot include tax
 * (there is no tax to include). The constructor rejects that combination.
 */
final readonly class TaxTreatment
{
    public bool $applyTax;

    public bool $taxIncludedInFee;

    private function __construct(bool $applyTax, bool $taxIncludedInFee)
    {
        if (! $applyTax && $taxIncludedInFee) {
            throw InvalidTaxTreatment::includedRequiresApplied();
        }

        $this->applyTax = $applyTax;
        $this->taxIncludedInFee = $taxIncludedInFee;
    }

    public static function of(bool $applyTax, bool $taxIncludedInFee): self
    {
        return new self($applyTax, $taxIncludedInFee);
    }

    public static function none(): self
    {
        return new self(false, false);
    }

    public function equals(self $other): bool
    {
        return $this->applyTax === $other->applyTax
            && $this->taxIncludedInFee === $other->taxIncludedInFee;
    }
}
