<?php

declare(strict_types=1);

namespace App\Tests\Support\Trait;

use App\Administration\Domain\Privilege;
use App\Administration\Domain\PrivilegeGrantSource;
use App\Administration\Domain\ValueObject\AdministratorId;
use App\Administration\Domain\ValueObject\GrantOrigin;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * Shared behavioural contract for any {@see PrivilegeGrantSource}
 * implementation (LRA-270): every source resolves an administrator's
 * privileges the same way regardless of the mechanism behind it, so the
 * same three cases apply uniformly to
 * {@see \App\Administration\Infrastructure\Persistence\Doctrine\Read\RankRoleGrants},
 * {@see \App\Administration\Infrastructure\Persistence\Doctrine\Read\DirectRoleGrants},
 * and {@see \App\Administration\Infrastructure\Persistence\InMemory\InMemoryPrivilegeGrantSource}
 * alike — LRA-271's support-access source reuses this trait rather than
 * writing its own.
 */
trait PrivilegeGrantSourceContractCases
{
    /**
     * Valid UUID v7 shape: the Doctrine-backed sources echo $sourceId
     * back as the real underlying role's id, so an arbitrary
     * non-UUID string (e.g. "source-1") would fail RoleId's own
     * validation in those concrete test classes' grantPrivilege()
     * implementations.
     */
    private const string SOURCE_ID_1 = '019571bf-5d51-7000-b500-00000000fc01';
    private const string SOURCE_ID_2 = '019571bf-5d51-7000-b500-00000000fc02';

    abstract protected function source(): PrivilegeGrantSource;

    /**
     * A fixed administrator identity the concrete test class seeds
     * (with a rank, roles etc. as its own mechanism requires) once per
     * test, used across all three cases below.
     */
    abstract protected function administratorId(): AdministratorId;

    /**
     * The {@see GrantOrigin} this source's grants are expected to carry.
     */
    abstract protected function expectedOrigin(): GrantOrigin;

    /**
     * Seeds this source's own mechanism so that
     * `source()->grantsFor(administratorId())` subsequently includes
     * $privilege, attributed to a thing identified by $sourceId and
     * named $sourceName.
     */
    abstract protected function grantPrivilege(Privilege $privilege, string $sourceId, string $sourceName): void;

    #[Test]
    #[TestDox('grantsFor() returns an empty set for an administrator with nothing granted through this source.')]
    public function returns_empty_set_when_nothing_granted(): void
    {
        $grants = $this->source()->grantsFor($this->administratorId());

        self::assertSame(0, $grants->count());
    }

    #[Test]
    #[TestDox('grantsFor() resolves a granted privilege with its origin, source id, and source name.')]
    public function resolves_a_granted_privilege_with_its_provenance(): void
    {
        $this->grantPrivilege(Privilege::ViewUsers, self::SOURCE_ID_1, 'Source One');

        $grants = $this->source()->grantsFor($this->administratorId());

        self::assertTrue($grants->privileges()->contains(Privilege::ViewUsers));
        $origin = $grants->originOf(Privilege::ViewUsers);
        self::assertNotNull($origin);
        self::assertSame($this->expectedOrigin(), $origin->origin);
        self::assertSame(self::SOURCE_ID_1, $origin->sourceId);
        self::assertSame('Source One', $origin->sourceName);
    }

    #[Test]
    #[TestDox('grantsFor() aggregates more than one privilege granted through this source.')]
    public function aggregates_multiple_granted_privileges(): void
    {
        $this->grantPrivilege(Privilege::ViewUsers, self::SOURCE_ID_1, 'Source One');
        $this->grantPrivilege(Privilege::AddUsers, self::SOURCE_ID_2, 'Source Two');

        $grants = $this->source()->grantsFor($this->administratorId());

        self::assertSame(
            [Privilege::ViewUsers->value, Privilege::AddUsers->value],
            $grants->privileges()->toNames(),
        );
    }
}
