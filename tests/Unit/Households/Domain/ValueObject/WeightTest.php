<?php

declare(strict_types=1);

namespace App\Tests\Unit\Households\Domain\ValueObject;

use App\Households\Domain\Exception\InvalidWeight;
use App\Households\Domain\ValueObject\Weight;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[Small]
final class WeightTest extends TestCase
{
    #[Test]
    #[TestDox('::ofPounds() constructs from a positive whole number of pounds.')]
    public function constructs_from_pounds(): void
    {
        $weight = Weight::ofPounds(180);

        self::assertSame(180, $weight->pounds);
    }

    #[Test]
    #[TestWith([0], 'zero')]
    #[TestWith([-5], 'negative')]
    #[TestDox('::ofPounds() rejects non-positive values with InvalidWeight.')]
    public function rejects_non_positive_pounds(int $pounds): void
    {
        $this->expectException(InvalidWeight::class);

        Weight::ofPounds($pounds);
    }

    #[Test]
    #[TestDox('::ofPounds() rejects a value exceeding the 1500-pound maximum with InvalidWeight.')]
    public function rejects_value_exceeding_maximum(): void
    {
        $this->expectException(InvalidWeight::class);

        Weight::ofPounds(1501);
    }

    #[Test]
    #[TestDox('Equals another Weight with the same pounds.')]
    public function equals(): void
    {
        self::assertTrue(Weight::ofPounds(180)->equals(Weight::ofPounds(180)));
        self::assertFalse(Weight::ofPounds(180)->equals(Weight::ofPounds(181)));
    }
}
