<?php

declare(strict_types=1);

namespace App\Tests\Unit\Inventory\Domain\ValueObject;

use App\Inventory\Domain\Exception\NegativeCost;
use App\Inventory\Domain\ValueObject\CostPerUnit;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[Small]
final class CostPerUnitTest extends TestCase
{
    /**
     * @return Generator<string, array{int}>
     */
    public static function nonNegativeCentCases(): Generator
    {
        yield 'zero'        => [0];
        yield 'one cent'    => [1];
        yield 'one dollar'  => [100];
        yield 'large value' => [9_999_999];
    }

    #[Test]
    #[DataProvider('nonNegativeCentCases')]
    #[TestDox('Accepts a non-negative cent amount: $_dataName.')]
    public function accepts_non_negative_cent_amount(int $cents): void
    {
        self::assertSame($cents, CostPerUnit::ofCents($cents)->cents);
    }

    /**
     * @return Generator<string, array{int}>
     */
    public static function negativeCentCases(): Generator
    {
        yield '-1'      => [-1];
        yield '-100'    => [-100];
    }

    #[Test]
    #[DataProvider('negativeCentCases')]
    #[TestDox('Rejects a negative cent amount with NegativeCost: $_dataName.')]
    public function rejects_negative_cent_amount(int $cents): void
    {
        $this->expectException(NegativeCost::class);

        CostPerUnit::ofCents($cents);
    }

    #[Test]
    #[TestDox('zero() returns a CostPerUnit of 0 cents.')]
    public function zero_returns_a_cost_per_unit_of_zero_cents(): void
    {
        self::assertSame(0, CostPerUnit::zero()->cents);
    }

    #[Test]
    #[TestDox('equals() compares by cent amount.')]
    public function equals_compares_by_cent_amount(): void
    {
        self::assertTrue(CostPerUnit::ofCents(250)->equals(CostPerUnit::ofCents(250)));
        self::assertFalse(CostPerUnit::ofCents(250)->equals(CostPerUnit::ofCents(251)));
    }
}
