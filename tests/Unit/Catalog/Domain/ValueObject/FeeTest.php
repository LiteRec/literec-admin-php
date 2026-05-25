<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Domain\ValueObject;

use App\Catalog\Domain\Exception\InvalidFee;
use App\Catalog\Domain\ValueObject\Currency;
use App\Catalog\Domain\ValueObject\Fee;
use App\Catalog\Domain\ValueObject\Money;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[Small]
final class FeeTest extends TestCase
{
    #[Test]
    #[TestDox('Constructs from a Money amount and a non-empty label, trimming surrounding whitespace.')]
    public function constructs_from_money_and_trimmed_label(): void
    {
        $fee = Fee::of(Money::ofCents(1500, Currency::USD), '  Adult  ');

        self::assertSame('Adult', $fee->label);
        self::assertSame(1500, $fee->amount->cents);
    }

    #[Test]
    #[TestDox('Rejects an empty label (after trim) with InvalidFee.')]
    public function rejects_empty_label(): void
    {
        $this->expectException(InvalidFee::class);

        Fee::of(Money::ofCents(0, Currency::USD), '   ');
    }

    #[Test]
    #[TestDox('Rejects a label longer than 64 characters with InvalidFee.')]
    public function rejects_overlong_label(): void
    {
        $this->expectException(InvalidFee::class);

        Fee::of(Money::ofCents(0, Currency::USD), str_repeat('a', 65));
    }

    #[Test]
    #[TestDox('Equals another Fee with the same label and amount.')]
    public function equals_compares_label_and_amount(): void
    {
        $a = Fee::of(Money::ofCents(1500, Currency::USD), 'Adult');
        $b = Fee::of(Money::ofCents(1500, Currency::USD), 'Adult');
        $c = Fee::of(Money::ofCents(1500, Currency::USD), 'Child');
        $d = Fee::of(Money::ofCents(1000, Currency::USD), 'Adult');

        self::assertTrue($a->equals($b));
        self::assertFalse($a->equals($c));
        self::assertFalse($a->equals($d));
    }
}
