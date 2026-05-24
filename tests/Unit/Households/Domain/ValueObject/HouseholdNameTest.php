<?php

declare(strict_types=1);

namespace App\Tests\Unit\Households\Domain\ValueObject;

use App\Households\Domain\Exception\InvalidHouseholdName;
use App\Households\Domain\ValueObject\HouseholdName;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[Small]
final class HouseholdNameTest extends TestCase
{
    #[Test]
    #[TestDox('Stores the trimmed value when given a valid name.')]
    public function accepts_a_valid_name(): void
    {
        self::assertSame('Smith Family', HouseholdName::of('Smith Family')->value);
    }

    #[Test]
    #[TestDox('Trims surrounding whitespace before storing.')]
    public function trims_surrounding_whitespace(): void
    {
        self::assertSame('Smith Family', HouseholdName::of('   Smith Family   ')->value);
    }

    #[Test]
    #[TestWith([''])]
    #[TestWith(['   '])]
    #[TestWith(["\t\n"])]
    #[TestDox('Rejects an empty or whitespace-only name with InvalidHouseholdName.')]
    public function rejects_empty(string $input): void
    {
        $this->expectException(InvalidHouseholdName::class);

        HouseholdName::of($input);
    }

    #[Test]
    #[TestDox('Rejects names longer than the 200-character limit.')]
    public function rejects_overlong_name(): void
    {
        $this->expectException(InvalidHouseholdName::class);

        HouseholdName::of(str_repeat('a', 201));
    }

    #[Test]
    #[TestDox('Accepts a name of exactly the maximum length.')]
    public function accepts_maximum_length(): void
    {
        $value = str_repeat('a', 200);

        self::assertSame($value, HouseholdName::of($value)->value);
    }

    #[Test]
    #[TestDox('Equals another HouseholdName with the same trimmed value.')]
    public function equals_another_name(): void
    {
        self::assertTrue(HouseholdName::of('Smith')->equals(HouseholdName::of(' Smith ')));
        self::assertFalse(HouseholdName::of('Smith')->equals(HouseholdName::of('Jones')));
    }

    #[Test]
    #[TestDox('Stringifies to the stored value.')]
    public function stringifies(): void
    {
        self::assertSame('Smith', (string) HouseholdName::of('Smith'));
    }
}
