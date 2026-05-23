<?php

declare(strict_types=1);

namespace App\Tests\Unit\Users\Domain\ValueObject;

use App\Users\Domain\ValueObject\Role;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use ValueError;

#[Small]
final class RoleTest extends TestCase
{
    #[Test]
    #[TestDox('Has exactly two cases, ROLE_USER and ROLE_ADMIN.')]
    public function has_exactly_two_cases(): void
    {
        self::assertSame(
            ['ROLE_USER', 'ROLE_ADMIN'],
            array_map(static fn(Role $r) => $r->value, Role::cases()),
        );
    }

    #[Test]
    #[TestDox('Round-trips a known value through ::from().')]
    public function round_trips_a_known_value_through_from(): void
    {
        self::assertSame(Role::Admin, Role::from('ROLE_ADMIN'));
        self::assertSame(Role::User, Role::from('ROLE_USER'));
    }

    #[Test]
    #[TestDox('Throws ValueError when ::from() receives an unknown role string.')]
    public function throws_value_error_on_unknown_role_string(): void
    {
        $this->expectException(ValueError::class);

        Role::from('ROLE_GHOST');
    }
}
