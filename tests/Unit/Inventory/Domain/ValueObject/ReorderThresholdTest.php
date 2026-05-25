<?php

declare(strict_types=1);

namespace App\Tests\Unit\Inventory\Domain\ValueObject;

use App\Inventory\Domain\Exception\InvalidReorderThreshold;
use App\Inventory\Domain\ValueObject\Quantity;
use App\Inventory\Domain\ValueObject\ReorderThreshold;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[Small]
final class ReorderThresholdTest extends TestCase
{
    #[Test]
    #[TestDox('none() returns a threshold that is never breached.')]
    public function none_returns_a_threshold_that_is_never_breached(): void
    {
        $threshold = ReorderThreshold::none();

        self::assertFalse($threshold->isSet());
        self::assertFalse($threshold->isBreached(Quantity::zero()));
        self::assertFalse($threshold->isBreached(Quantity::ofUnits(1_000_000)));
    }

    #[Test]
    #[TestDox('ofUnits() with zero or positive is accepted.')]
    public function of_units_with_zero_or_positive_is_accepted(): void
    {
        self::assertSame(0, ReorderThreshold::ofUnits(0)->units);
        self::assertSame(5, ReorderThreshold::ofUnits(5)->units);
    }

    #[Test]
    #[TestDox('ofUnits() with negative throws InvalidReorderThreshold.')]
    public function of_units_with_negative_throws(): void
    {
        $this->expectException(InvalidReorderThreshold::class);

        ReorderThreshold::ofUnits(-1);
    }

    #[Test]
    #[TestDox('InvalidReorderThreshold factory returns a usable exception instance.')]
    public function invalid_reorder_threshold_factory_returns_usable_instance(): void
    {
        $exception = InvalidReorderThreshold::for(-2);

        self::assertStringContainsString('-2', $exception->getMessage());
    }

    #[Test]
    #[TestDox('isBreached() is true when on-hand is at or below the threshold.')]
    public function is_breached_when_on_hand_is_at_or_below_threshold(): void
    {
        $threshold = ReorderThreshold::ofUnits(5);

        self::assertTrue($threshold->isBreached(Quantity::ofUnits(5)));
        self::assertTrue($threshold->isBreached(Quantity::ofUnits(4)));
        self::assertTrue($threshold->isBreached(Quantity::zero()));
        self::assertFalse($threshold->isBreached(Quantity::ofUnits(6)));
    }

    #[Test]
    #[TestDox('equals() compares by unit count including the none() sentinel.')]
    public function equals_compares_by_unit_count_including_sentinel(): void
    {
        self::assertTrue(ReorderThreshold::none()->equals(ReorderThreshold::none()));
        self::assertTrue(ReorderThreshold::ofUnits(3)->equals(ReorderThreshold::ofUnits(3)));
        self::assertFalse(ReorderThreshold::ofUnits(3)->equals(ReorderThreshold::ofUnits(4)));
        self::assertFalse(ReorderThreshold::none()->equals(ReorderThreshold::ofUnits(0)));
    }
}
