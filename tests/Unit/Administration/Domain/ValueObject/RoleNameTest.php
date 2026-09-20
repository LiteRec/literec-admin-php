<?php

declare(strict_types=1);

namespace App\Tests\Unit\Administration\Domain\ValueObject;

use App\Administration\Domain\Exception\InvalidRoleName;
use App\Administration\Domain\ValueObject\RoleName;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[Small]
final class RoleNameTest extends TestCase
{
    #[Test]
    #[TestWith([''], 'empty')]
    #[TestWith(['   '], 'whitespace-only')]
    #[TestDox('Rejects an empty (or whitespace-only) name with InvalidRoleName.')]
    public function rejects_empty_name(string $value): void
    {
        $this->expectException(InvalidRoleName::class);

        self::assertInstanceOf(RoleName::class, RoleName::of($value));
    }

    #[Test]
    #[TestDox('Rejects a name longer than 80 characters with InvalidRoleName.')]
    public function rejects_a_name_that_is_too_long(): void
    {
        $this->expectException(InvalidRoleName::class);

        self::assertInstanceOf(RoleName::class, RoleName::of(str_repeat('a', RoleName::MAX_LENGTH + 1)));
    }

    #[Test]
    #[TestDox('Trims surrounding whitespace.')]
    public function trims_surrounding_whitespace(): void
    {
        $name = RoleName::of('  Front Desk  ');

        self::assertSame('Front Desk', $name->value);
    }

    #[Test]
    #[TestDox('equals() is case-insensitive.')]
    public function equals_is_case_insensitive(): void
    {
        $a = RoleName::of('Front Desk');
        $b = RoleName::of('front desk');
        $c = RoleName::of('Facility Manager');

        self::assertTrue($a->equals($b));
        self::assertFalse($a->equals($c));
    }

    #[Test]
    #[TestDox('isIdenticalTo() compares byte-exactly, unlike the case-insensitive equals() (LRA-280).')]
    public function is_identical_to_compares_byte_exactly(): void
    {
        $a = RoleName::of('Front Desk');
        $b = RoleName::of('front desk');
        $c = RoleName::of('Front Desk');

        self::assertTrue($a->equals($b));
        self::assertFalse($a->isIdenticalTo($b));
        self::assertTrue($a->isIdenticalTo($c));
    }
}
