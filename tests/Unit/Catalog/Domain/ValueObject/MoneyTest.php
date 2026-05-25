<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Domain\ValueObject;

use App\Catalog\Domain\Exception\InvalidMoney;
use App\Catalog\Domain\ValueObject\Currency;
use App\Catalog\Domain\ValueObject\Money;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[Small]
final class MoneyTest extends TestCase
{
    #[Test]
    #[TestWith([0], 'zero')]
    #[TestWith([1], 'one cent')]
    #[TestWith([12345], 'arbitrary positive')]
    #[TestDox('Accepts zero or positive cent amounts: $_dataName.')]
    public function accepts_zero_or_positive_cents(int $cents): void
    {
        $money = Money::ofCents($cents, Currency::USD);

        self::assertSame($cents, $money->cents);
        self::assertSame(Currency::USD, $money->currency);
    }

    #[Test]
    #[TestDox('Rejects a negative cent amount with InvalidMoney.')]
    public function rejects_negative_amounts(): void
    {
        $this->expectException(InvalidMoney::class);

        Money::ofCents(-1, Currency::USD);
    }

    #[Test]
    #[TestDox('Money::zero constructs a zero amount in the given currency.')]
    public function zero_constructor_produces_zero(): void
    {
        $zero = Money::zero(Currency::USD);

        self::assertSame(0, $zero->cents);
        self::assertSame(Currency::USD, $zero->currency);
    }

    #[Test]
    #[TestDox(
        'Equals another Money with the same cents. Currency comparison is deferred until '
        . 'Currency gains a second case (single-case enum makes the check a PHPStan tautology).'
    )]
    public function equals_compares_cents(): void
    {
        $a = Money::ofCents(100, Currency::USD);
        $b = Money::ofCents(100, Currency::USD);
        $c = Money::ofCents(101, Currency::USD);

        self::assertTrue($a->equals($b));
        self::assertFalse($a->equals($c));
    }
}
