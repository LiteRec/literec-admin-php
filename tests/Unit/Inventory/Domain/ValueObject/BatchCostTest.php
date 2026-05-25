<?php

declare(strict_types=1);

namespace App\Tests\Unit\Inventory\Domain\ValueObject;

use App\Inventory\Domain\Exception\QuantityOverflow;
use App\Inventory\Domain\ValueObject\BatchCost;
use App\Inventory\Domain\ValueObject\CostPerUnit;
use App\Inventory\Domain\ValueObject\Quantity;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[Small]
final class BatchCostTest extends TestCase
{
    #[Test]
    #[TestDox('compute() multiplies cost-per-unit by quantity.')]
    public function compute_multiplies_cost_per_unit_by_quantity(): void
    {
        $batch = BatchCost::compute(CostPerUnit::ofCents(250), Quantity::ofUnits(4));

        self::assertSame(1000, $batch->cents);
    }

    #[Test]
    #[TestDox('compute() yields zero when either operand is zero.')]
    public function compute_yields_zero_when_either_operand_is_zero(): void
    {
        self::assertSame(0, BatchCost::compute(CostPerUnit::zero(), Quantity::ofUnits(10))->cents);
        self::assertSame(0, BatchCost::compute(CostPerUnit::ofCents(250), Quantity::zero())->cents);
    }

    #[Test]
    #[TestDox('compute() throws QuantityOverflow when the multiplication would exceed PHP_INT_MAX.')]
    public function compute_throws_overflow_when_multiplication_exceeds_php_int_max(): void
    {
        $this->expectException(QuantityOverflow::class);

        BatchCost::compute(
            CostPerUnit::ofCents(intdiv(PHP_INT_MAX, 2) + 1),
            Quantity::ofUnits(3),
        );
    }

    #[Test]
    #[TestDox('equals() compares by cent amount.')]
    public function equals_compares_by_cent_amount(): void
    {
        self::assertTrue(BatchCost::ofCents(500)->equals(BatchCost::ofCents(500)));
        self::assertFalse(BatchCost::ofCents(500)->equals(BatchCost::ofCents(501)));
    }
}
