<?php

declare(strict_types=1);

namespace App\Tests\Support\Trait;

use App\Administration\Domain\Privilege;
use App\Administration\Domain\PrivilegeGrantSource;
use App\Administration\Domain\Roles;
use App\Administration\Domain\ValueObject\Actor;
use App\Administration\Domain\ValueObject\AdministratorId;
use App\Administration\Domain\ValueObject\RoleId;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Component\Clock\MockClock;

/**
 * Shared "retiring the granting role removes its privileges" case for
 * {@see \App\Tests\Integration\Administration\Persistence\Read\RankRoleGrantsTest}
 * and {@see \App\Tests\Integration\Administration\Persistence\Read\DirectRoleGrantsTest}
 * — the two Doctrine-backed {@see PrivilegeGrantSource} adapters, both of
 * which filter on `administration_roles.retired`.
 *
 * Deliberately NOT part of {@see PrivilegeGrantSourceContractCases}: role
 * retirement is not something
 * {@see \App\Administration\Infrastructure\Persistence\InMemory\InMemoryPrivilegeGrantSource}
 * can express (it has no backing Role aggregate at all), so this case
 * would have no meaningful implementation there.
 */
trait RetiredRoleGrantsNothingCase
{
    private const string RETIRED_ROLE_ID = '019571bf-5d51-7000-b500-00000000fc09';

    abstract protected function source(): PrivilegeGrantSource;

    abstract protected function administratorId(): AdministratorId;

    abstract protected function roles(): Roles;

    abstract protected function clock(): MockClock;

    abstract protected function grantPrivilege(Privilege $privilege, string $sourceId, string $sourceName): void;

    #[Test]
    #[TestDox('grantsFor() stops reporting a privilege once the granting role is retired.')]
    public function retired_role_grants_nothing(): void
    {
        $this->grantPrivilege(Privilege::ViewUsers, self::RETIRED_ROLE_ID, 'Front Desk');
        self::assertTrue(
            $this->source()->grantsFor($this->administratorId())->privileges()->contains(Privilege::ViewUsers),
        );

        $role = $this->roles()->byId(RoleId::fromString(self::RETIRED_ROLE_ID));
        $role->retire(Actor::system(), $this->clock());
        $role->releaseEvents();
        $this->roles()->save($role);

        self::assertSame(0, $this->source()->grantsFor($this->administratorId())->count());
    }
}
