<?php

declare(strict_types=1);

namespace App\Catalog\Domain\ValueObject;

use App\Catalog\Domain\Exception\InvalidMoney;

/**
 * Minor-unit-based monetary amount paired with a currency.
 *
 * Catalog only allows non-negative amounts — fee values, list prices, and
 * tax components are all zero-or-positive. Refunds and negative
 * line-items are modelled by the Transactions context, not Catalog.
 */
final readonly class Money
{
    public int $cents;

    public Currency $currency;

    private function __construct(int $cents, Currency $currency)
    {
        if ($cents < 0) {
            throw InvalidMoney::negativeAmount($cents);
        }

        $this->cents = $cents;
        $this->currency = $currency;
    }

    public static function ofCents(int $cents, Currency $currency): self
    {
        return new self($cents, $currency);
    }

    public static function zero(Currency $currency): self
    {
        return new self(0, $currency);
    }

    public function equals(self $other): bool
    {
        // Currency comparison is omitted while the Currency enum has a
        // single case (PHPStan flags it as a tautology); add it back the
        // moment a second case is introduced.
        return $this->cents === $other->cents;
    }
}
