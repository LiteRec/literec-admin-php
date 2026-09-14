<?php

declare(strict_types=1);

namespace App\Tests\Unit\Households\Domain\ValueObject;

use App\Households\Domain\Exception\InvalidHeight;
use App\Households\Domain\ValueObject\Height;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[Small]
final class HeightTest extends TestCase
{
    #[Test]
    #[TestDox('::ofInches() constructs from a positive whole number of inches.')]
    public function constructs_from_inches(): void
    {
        $height = Height::ofInches(71);

        self::assertSame(71, $height->inches);
    }

    #[Test]
    #[TestWith([0], 'zero')]
    #[TestWith([-5], 'negative')]
    #[TestDox('::ofInches() rejects non-positive values with InvalidHeight.')]
    public function rejects_non_positive_inches(int $inches): void
    {
        $this->expectException(InvalidHeight::class);

        Height::ofInches($inches);
    }

    #[Test]
    #[TestDox('::ofInches() rejects a value exceeding the 107-inch (8 ft 11 in) maximum with InvalidHeight.')]
    public function rejects_value_exceeding_maximum(): void
    {
        $this->expectException(InvalidHeight::class);

        Height::ofInches(108);
    }

    #[Test]
    #[TestDox('feet() and remainingInches() split total inches as floor division and modulo.')]
    public function feet_and_remaining_inches_split_total(): void
    {
        $height = Height::ofInches(71);

        self::assertSame(5, $height->feet());
        self::assertSame(11, $height->remainingInches());
    }

    #[Test]
    #[TestDox('Equals another Height with the same inches.')]
    public function equals(): void
    {
        self::assertTrue(Height::ofInches(71)->equals(Height::ofInches(71)));
        self::assertFalse(Height::ofInches(71)->equals(Height::ofInches(72)));
    }
}
