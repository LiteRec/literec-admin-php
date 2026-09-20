<?php

declare(strict_types=1);

namespace App\Tests\Unit\Administration\Domain\ValueObject;

use App\Administration\Domain\Privilege;
use App\Administration\Domain\ValueObject\PrivilegeSet;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[Small]
final class PrivilegeSetTest extends TestCase
{
    #[Test]
    #[TestDox('none() is empty.')]
    public function none_is_empty(): void
    {
        self::assertSame(0, PrivilegeSet::none()->count());
        self::assertSame([], PrivilegeSet::none()->toList());
    }

    #[Test]
    #[TestDox('of() de-duplicates, first occurrence wins.')]
    public function of_deduplicates(): void
    {
        $set = PrivilegeSet::of(Privilege::ViewUsers, Privilege::ViewUsers, Privilege::AddUsers);

        self::assertSame(2, $set->count());
        self::assertSame(
            [Privilege::ViewUsers->value, Privilege::AddUsers->value],
            $set->toNames(),
        );
    }

    #[Test]
    #[TestDox('contains() reports membership.')]
    public function contains_reports_membership(): void
    {
        $set = PrivilegeSet::of(Privilege::ViewUsers);

        self::assertTrue($set->contains(Privilege::ViewUsers));
        self::assertFalse($set->contains(Privilege::AddUsers));
    }

    #[Test]
    #[TestDox('with() adds a privilege and returns a new instance, leaving the original untouched.')]
    public function with_adds_and_is_immutable(): void
    {
        $original = PrivilegeSet::of(Privilege::ViewUsers);
        $extended = $original->with(Privilege::AddUsers);

        self::assertFalse($original->contains(Privilege::AddUsers));
        self::assertTrue($extended->contains(Privilege::AddUsers));
        self::assertTrue($extended->contains(Privilege::ViewUsers));
    }

    #[Test]
    #[TestDox('with() is a no-op (returns the same instance) when the privilege is already present.')]
    public function with_is_a_no_op_when_already_present(): void
    {
        $set = PrivilegeSet::of(Privilege::ViewUsers);

        self::assertSame($set, $set->with(Privilege::ViewUsers));
    }

    #[Test]
    #[TestDox('without() removes a privilege and returns a new instance, leaving the original untouched.')]
    public function without_removes_and_is_immutable(): void
    {
        $original = PrivilegeSet::of(Privilege::ViewUsers, Privilege::AddUsers);
        $reduced = $original->without(Privilege::AddUsers);

        self::assertTrue($original->contains(Privilege::AddUsers));
        self::assertFalse($reduced->contains(Privilege::AddUsers));
        self::assertTrue($reduced->contains(Privilege::ViewUsers));
    }

    #[Test]
    #[TestDox('without() is a no-op (returns the same instance) when the privilege is absent.')]
    public function without_is_a_no_op_when_absent(): void
    {
        $set = PrivilegeSet::of(Privilege::ViewUsers);

        self::assertSame($set, $set->without(Privilege::AddUsers));
    }

    #[Test]
    #[TestDox('equals() compares as sets, ignoring order.')]
    public function equals_ignores_order(): void
    {
        $a = PrivilegeSet::of(Privilege::ViewUsers, Privilege::AddUsers);
        $b = PrivilegeSet::of(Privilege::AddUsers, Privilege::ViewUsers);
        $c = PrivilegeSet::of(Privilege::ViewUsers);

        self::assertTrue($a->equals($b));
        self::assertFalse($a->equals($c));
    }
}
