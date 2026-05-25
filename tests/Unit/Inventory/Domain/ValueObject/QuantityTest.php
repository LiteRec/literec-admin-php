<?php

declare(strict_types=1);

namespace App\Tests\Unit\Inventory\Domain\ValueObject;

use App\Inventory\Domain\Exception\QuantityOverflow;
use App\Inventory\Domain\Exception\QuantityWouldGoNegative;
use App\Inventory\Domain\ValueObject\Quantity;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[Small]
final class QuantityTest extends TestCase
{
    #[Test]
    #[TestDox('zero() returns a Quantity of 0 units.')]
    public function zero_returns_a_quantity_of_zero_units(): void
    {
        self::assertSame(0, Quantity::zero()->units);
        self::assertTrue(Quantity::zero()->isZero());
    }

    /**
     * @return Generator<string, array{int}>
     */
    public static function nonNegativeCases(): Generator
    {
        yield '0'                => [0];
        yield '1'                => [1];
        yield 'large positive'   => [1_000_000];
        yield 'PHP_INT_MAX'      => [PHP_INT_MAX];
    }

    #[Test]
    #[DataProvider('nonNegativeCases')]
    #[TestDox('Accepts a non-negative unit count: $_dataName.')]
    public function accepts_non_negative_unit_count(int $units): void
    {
        self::assertSame($units, Quantity::ofUnits($units)->units);
    }

    /**
     * @return Generator<string, array{int}>
     */
    public static function negativeCases(): Generator
    {
        yield '-1'              => [-1];
        yield '-1000'           => [-1000];
        yield 'PHP_INT_MIN + 1' => [PHP_INT_MIN + 1];
        yield 'PHP_INT_MIN'     => [PHP_INT_MIN];
    }

    #[Test]
    #[DataProvider('negativeCases')]
    #[TestDox('Rejects a negative unit count with QuantityWouldGoNegative: $_dataName.')]
    public function rejects_negative_unit_count(int $units): void
    {
        $this->expectException(QuantityWouldGoNegative::class);

        Quantity::ofUnits($units);
    }

    #[Test]
    #[TestDox('add() returns a new Quantity with the summed units.')]
    public function add_returns_a_new_quantity_with_the_summed_units(): void
    {
        $result = Quantity::ofUnits(3)->add(Quantity::ofUnits(4));

        self::assertSame(7, $result->units);
    }

    #[Test]
    #[TestDox('add() throws QuantityOverflow when result exceeds PHP_INT_MAX.')]
    public function add_throws_when_result_exceeds_php_int_max(): void
    {
        $this->expectException(QuantityOverflow::class);

        Quantity::ofUnits(PHP_INT_MAX)->add(Quantity::ofUnits(1));
    }

    #[Test]
    #[TestDox('subtract() returns a new Quantity with the difference.')]
    public function subtract_returns_a_new_quantity_with_the_difference(): void
    {
        $result = Quantity::ofUnits(10)->subtract(Quantity::ofUnits(3));

        self::assertSame(7, $result->units);
    }

    #[Test]
    #[TestDox('subtract() to exact zero is allowed.')]
    public function subtract_to_exact_zero_is_allowed(): void
    {
        $result = Quantity::ofUnits(5)->subtract(Quantity::ofUnits(5));

        self::assertTrue($result->isZero());
    }

    #[Test]
    #[TestDox('subtract() throws QuantityWouldGoNegative when result would be negative.')]
    public function subtract_throws_when_result_would_be_negative(): void
    {
        $this->expectException(QuantityWouldGoNegative::class);

        Quantity::ofUnits(3)->subtract(Quantity::ofUnits(5));
    }

    #[Test]
    #[TestDox('greaterThanOrEqual() compares unit counts.')]
    public function greater_than_or_equal_compares_unit_counts(): void
    {
        self::assertTrue(Quantity::ofUnits(5)->greaterThanOrEqual(Quantity::ofUnits(5)));
        self::assertTrue(Quantity::ofUnits(6)->greaterThanOrEqual(Quantity::ofUnits(5)));
        self::assertFalse(Quantity::ofUnits(4)->greaterThanOrEqual(Quantity::ofUnits(5)));
    }

    #[Test]
    #[TestDox('equals() compares by unit count.')]
    public function equals_compares_by_unit_count(): void
    {
        self::assertTrue(Quantity::ofUnits(7)->equals(Quantity::ofUnits(7)));
        self::assertFalse(Quantity::ofUnits(7)->equals(Quantity::ofUnits(8)));
    }
}
