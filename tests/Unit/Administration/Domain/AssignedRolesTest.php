<?php

declare(strict_types=1);

namespace App\Tests\Unit\Administration\Domain;

use App\Administration\Domain\ValueObject\AssignedRoles;
use App\Administration\Domain\ValueObject\RoleId;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[Small]
final class AssignedRolesTest extends TestCase
{
    private const string ROLE_A = '019571bf-5d51-7000-b500-00000000da01';
    private const string ROLE_B = '019571bf-5d51-7000-b500-00000000da02';

    #[Test]
    #[TestDox('of() de-duplicates equal-by-value RoleIds, keeping the first occurrence.')]
    public function of_deduplicates_by_value(): void
    {
        $roles = AssignedRoles::of(
            RoleId::fromString(self::ROLE_A),
            RoleId::fromString(self::ROLE_A),
            RoleId::fromString(self::ROLE_B),
        );

        self::assertSame(2, $roles->count());
    }

    #[Test]
    #[TestDox('with() adds a role and returns a new instance, leaving the original untouched.')]
    public function with_returns_a_new_instance(): void
    {
        $original = AssignedRoles::none();
        $roleId = RoleId::fromString(self::ROLE_A);

        $withRole = $original->with($roleId);

        self::assertFalse($original->contains($roleId));
        self::assertTrue($withRole->contains($roleId));
        self::assertNotSame($original, $withRole);
    }

    #[Test]
    #[TestDox('with() is idempotent: adding an already-contained role returns the same instance.')]
    public function with_is_idempotent(): void
    {
        $roleId = RoleId::fromString(self::ROLE_A);
        $roles = AssignedRoles::of($roleId);

        self::assertSame($roles, $roles->with(RoleId::fromString(self::ROLE_A)));
    }

    #[Test]
    #[TestDox('without() removes a role and returns a new instance, leaving the original untouched.')]
    public function without_returns_a_new_instance(): void
    {
        $roleId = RoleId::fromString(self::ROLE_A);
        $original = AssignedRoles::of($roleId);

        $withoutRole = $original->without($roleId);

        self::assertTrue($original->contains($roleId));
        self::assertFalse($withoutRole->contains($roleId));
        self::assertNotSame($original, $withoutRole);
    }

    #[Test]
    #[TestDox('without() is idempotent: removing a role that is not present returns the same instance.')]
    public function without_is_idempotent(): void
    {
        $roles = AssignedRoles::none();

        self::assertSame($roles, $roles->without(RoleId::fromString(self::ROLE_A)));
    }

    #[Test]
    #[TestDox('equals() is order-insensitive.')]
    public function equals_is_order_insensitive(): void
    {
        $a = AssignedRoles::of(RoleId::fromString(self::ROLE_A), RoleId::fromString(self::ROLE_B));
        $b = AssignedRoles::of(RoleId::fromString(self::ROLE_B), RoleId::fromString(self::ROLE_A));

        self::assertTrue($a->equals($b));
    }

    #[Test]
    #[TestDox('equals() returns false when the sets differ.')]
    public function equals_returns_false_for_different_sets(): void
    {
        $a = AssignedRoles::of(RoleId::fromString(self::ROLE_A));
        $b = AssignedRoles::of(RoleId::fromString(self::ROLE_B));

        self::assertFalse($a->equals($b));
    }

    #[Test]
    #[TestDox('toList() returns the roles in de-duplicated insertion order.')]
    public function to_list_returns_de_duplicated_roles(): void
    {
        $roleA = RoleId::fromString(self::ROLE_A);
        $roleB = RoleId::fromString(self::ROLE_B);
        $roles = AssignedRoles::of($roleA, $roleB);

        self::assertEquals([$roleA, $roleB], $roles->toList());
    }
}
