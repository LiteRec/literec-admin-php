<?php

declare(strict_types=1);

namespace App\Tests\Unit\Administration\Domain\ValueObject;

use App\Administration\Domain\Exception\InvalidRoleDescription;
use App\Administration\Domain\ValueObject\RoleDescription;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[Small]
final class RoleDescriptionTest extends TestCase
{
    #[Test]
    #[TestDox('empty() constructs the empty description, never null.')]
    public function empty_constructs_the_empty_value(): void
    {
        self::assertSame('', RoleDescription::empty()->value);
    }

    #[Test]
    #[TestDox('of("") is equal to empty().')]
    public function of_empty_string_equals_the_empty_named_constructor(): void
    {
        self::assertTrue(RoleDescription::of('')->equals(RoleDescription::empty()));
    }

    #[Test]
    #[TestDox('Trims surrounding whitespace.')]
    public function trims_surrounding_whitespace(): void
    {
        $description = RoleDescription::of('  Front-of-house operations.  ');

        self::assertSame('Front-of-house operations.', $description->value);
    }

    #[Test]
    #[TestDox('Rejects a description longer than 1000 characters with InvalidRoleDescription.')]
    public function rejects_a_description_that_is_too_long(): void
    {
        $this->expectException(InvalidRoleDescription::class);

        self::assertInstanceOf(
            RoleDescription::class,
            RoleDescription::of(str_repeat('a', RoleDescription::MAX_LENGTH + 1)),
        );
    }

    #[Test]
    #[TestDox('equals() compares by value.')]
    public function equals_compares_by_value(): void
    {
        $a = RoleDescription::of('Same');
        $b = RoleDescription::of('Same');
        $c = RoleDescription::of('Different');

        self::assertTrue($a->equals($b));
        self::assertFalse($a->equals($c));
    }
}
