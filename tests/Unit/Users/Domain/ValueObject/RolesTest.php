<?php

declare(strict_types=1);

namespace App\Tests\Unit\Users\Domain\ValueObject;

use App\Users\Domain\ValueObject\Role;
use App\Users\Domain\ValueObject\Roles;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[Small]
final class RolesTest extends TestCase
{
    #[Test]
    #[TestDox('::of() de-duplicates equal roles, first occurrence wins.')]
    public function of_deduplicates(): void
    {
        $roles = Roles::of(Role::User, Role::Admin, Role::User);

        self::assertSame(2, $roles->count());
        self::assertSame([Role::User, Role::Admin], $roles->toList());
    }

    #[Test]
    #[TestDox('::none() produces an empty, valid collection.')]
    public function none_produces_an_empty_collection(): void
    {
        $roles = Roles::none();

        self::assertSame(0, $roles->count());
        self::assertSame([], $roles->toList());
    }

    #[Test]
    #[TestDox('::contains() reports membership.')]
    public function contains_reports_membership(): void
    {
        $roles = Roles::of(Role::User);

        self::assertTrue($roles->contains(Role::User));
        self::assertFalse($roles->contains(Role::Admin));
    }

    #[Test]
    #[TestDox('::with() adds a role not yet present, returning a new instance.')]
    public function with_adds_a_new_role(): void
    {
        $roles = Roles::of(Role::User);

        $withAdmin = $roles->with(Role::Admin);

        self::assertNotSame($roles, $withAdmin);
        self::assertTrue($withAdmin->contains(Role::Admin));
        self::assertFalse($roles->contains(Role::Admin), 'The original instance must stay unchanged.');
    }

    #[Test]
    #[TestDox('::with() is a no-op when the role is already present, returning the same instance.')]
    public function with_is_idempotent(): void
    {
        $roles = Roles::of(Role::User);

        self::assertSame($roles, $roles->with(Role::User));
    }

    #[Test]
    #[TestDox('::without() removes a present role, returning a new instance.')]
    public function without_removes_a_present_role(): void
    {
        $roles = Roles::of(Role::User, Role::Admin);

        $withoutAdmin = $roles->without(Role::Admin);

        self::assertNotSame($roles, $withoutAdmin);
        self::assertFalse($withoutAdmin->contains(Role::Admin));
        self::assertTrue($roles->contains(Role::Admin), 'The original instance must stay unchanged.');
    }

    #[Test]
    #[TestDox('::without() is a no-op when the role is absent, returning the same instance.')]
    public function without_is_idempotent(): void
    {
        $roles = Roles::of(Role::User);

        self::assertSame($roles, $roles->without(Role::Admin));
    }

    #[Test]
    #[TestDox('::toStrings() projects the backing enum values.')]
    public function to_strings_projects_enum_values(): void
    {
        $roles = Roles::of(Role::User, Role::Admin);

        self::assertSame(['ROLE_USER', 'ROLE_ADMIN'], $roles->toStrings());
    }

    #[Test]
    #[TestDox('::equals() compares collections by their member set, order-independent.')]
    public function equals_compares_by_member_set(): void
    {
        $a = Roles::of(Role::User, Role::Admin);
        $b = Roles::of(Role::Admin, Role::User);
        $c = Roles::of(Role::User);

        self::assertTrue($a->equals($b));
        self::assertFalse($a->equals($c));
    }
}
