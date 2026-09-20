<?php

declare(strict_types=1);

namespace App\Tests\Unit\Administration\Domain\ValueObject;

use App\Administration\Domain\Privilege;
use App\Administration\Domain\ValueObject\GrantOrigin;
use App\Administration\Domain\ValueObject\PrivilegeGrant;
use App\Administration\Domain\ValueObject\PrivilegeGrants;
use App\Administration\Domain\ValueObject\PrivilegeSet;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[Small]
final class PrivilegeGrantsTest extends TestCase
{
    #[Test]
    #[TestDox('none() is empty.')]
    public function none_is_empty(): void
    {
        self::assertSame(0, PrivilegeGrants::none()->count());
        self::assertSame([], PrivilegeGrants::none()->all());
        self::assertTrue(PrivilegeGrants::none()->privileges()->equals(PrivilegeSet::none()));
    }

    #[Test]
    #[TestDox('privileges() is the de-duplicated set of privileges any contributing grant names.')]
    public function privileges_is_deduplicated(): void
    {
        $grants = PrivilegeGrants::of(
            new PrivilegeGrant(Privilege::ViewUsers, GrantOrigin::RankRole, 'role-1', 'Front Desk'),
            new PrivilegeGrant(Privilege::AddUsers, GrantOrigin::RankRole, 'role-1', 'Front Desk'),
        );

        self::assertSame(
            [Privilege::ViewUsers->value, Privilege::AddUsers->value],
            $grants->privileges()->toNames(),
        );
    }

    #[Test]
    #[TestDox('originOf() returns the first grant contributing a privilege.')]
    public function origin_of_returns_first_contributing_grant(): void
    {
        $grants = PrivilegeGrants::of(
            new PrivilegeGrant(Privilege::ViewUsers, GrantOrigin::RankRole, 'role-1', 'Front Desk'),
        );

        $origin = $grants->originOf(Privilege::ViewUsers);

        self::assertNotNull($origin);
        self::assertSame('role-1', $origin->sourceId);
    }

    #[Test]
    #[TestDox('originOf() returns null when nothing grants the privilege.')]
    public function origin_of_returns_null_when_not_granted(): void
    {
        self::assertNull(PrivilegeGrants::none()->originOf(Privilege::ViewUsers));
    }

    #[Test]
    #[TestDox('merge() retains both grants, not only the primary, when two sources grant the same privilege.')]
    public function merge_retains_every_contributing_grant_not_only_the_primary(): void
    {
        $fromRank = PrivilegeGrants::of(
            new PrivilegeGrant(Privilege::ViewUsers, GrantOrigin::RankRole, 'role-1', 'Front Desk'),
        );
        $fromDirect = PrivilegeGrants::of(
            new PrivilegeGrant(Privilege::ViewUsers, GrantOrigin::DirectRole, 'role-2', 'Backup Cashier'),
        );

        $merged = $fromRank->merge($fromDirect);

        // Both grants are retained and individually reachable...
        self::assertCount(2, $merged->all());
        // ...but the recorded origin for the privilege is the first
        // contributing grant (from $fromRank), not the second.
        $origin = $merged->originOf(Privilege::ViewUsers);
        self::assertNotNull($origin);
        self::assertSame(GrantOrigin::RankRole, $origin->origin);
        self::assertSame('role-1', $origin->sourceId);
        // ...and the deduplicated privilege set still reports it once.
        self::assertSame([Privilege::ViewUsers->value], $merged->privileges()->toNames());
    }

    #[Test]
    #[TestDox('merge() with an empty collection changes nothing.')]
    public function merge_with_empty_changes_nothing(): void
    {
        $grants = PrivilegeGrants::of(
            new PrivilegeGrant(Privilege::ViewUsers, GrantOrigin::RankRole, 'role-1', 'Front Desk'),
        );

        $merged = $grants->merge(PrivilegeGrants::none());

        self::assertTrue($merged->equals($grants));
    }

    #[Test]
    #[TestDox('equals() compares as sets of grants, ignoring order.')]
    public function equals_ignores_order(): void
    {
        $a = PrivilegeGrants::of(
            new PrivilegeGrant(Privilege::ViewUsers, GrantOrigin::RankRole, 'role-1', 'Front Desk'),
            new PrivilegeGrant(Privilege::AddUsers, GrantOrigin::DirectRole, 'role-2', 'Backup Cashier'),
        );
        $b = PrivilegeGrants::of(
            new PrivilegeGrant(Privilege::AddUsers, GrantOrigin::DirectRole, 'role-2', 'Backup Cashier'),
            new PrivilegeGrant(Privilege::ViewUsers, GrantOrigin::RankRole, 'role-1', 'Front Desk'),
        );
        $c = PrivilegeGrants::of(
            new PrivilegeGrant(Privilege::ViewUsers, GrantOrigin::RankRole, 'role-1', 'Front Desk'),
        );

        self::assertTrue($a->equals($b));
        self::assertFalse($a->equals($c));
    }

    #[Test]
    #[TestDox('PrivilegeGrant::equals() compares every field.')]
    public function privilege_grant_equals_compares_every_field(): void
    {
        $grant = new PrivilegeGrant(Privilege::ViewUsers, GrantOrigin::RankRole, 'role-1', 'Front Desk');

        self::assertTrue($grant->equals(
            new PrivilegeGrant(Privilege::ViewUsers, GrantOrigin::RankRole, 'role-1', 'Front Desk'),
        ));
        self::assertFalse($grant->equals(
            new PrivilegeGrant(Privilege::AddUsers, GrantOrigin::RankRole, 'role-1', 'Front Desk'),
        ));
        self::assertFalse($grant->equals(
            new PrivilegeGrant(Privilege::ViewUsers, GrantOrigin::DirectRole, 'role-1', 'Front Desk'),
        ));
        self::assertFalse($grant->equals(
            new PrivilegeGrant(Privilege::ViewUsers, GrantOrigin::RankRole, 'role-2', 'Front Desk'),
        ));
        self::assertFalse($grant->equals(
            new PrivilegeGrant(Privilege::ViewUsers, GrantOrigin::RankRole, 'role-1', 'Other Role'),
        ));
    }
}
