<?php

declare(strict_types=1);

namespace App\Administration\Infrastructure\Persistence\InMemory;

use App\Administration\Domain\Privilege;
use App\Administration\Domain\PrivilegeGrantSource;
use App\Administration\Domain\ValueObject\AdministratorId;
use App\Administration\Domain\ValueObject\GrantOrigin;
use App\Administration\Domain\ValueObject\PrivilegeGrant;
use App\Administration\Domain\ValueObject\PrivilegeGrants;

/**
 * In-memory {@see PrivilegeGrantSource} adapter (LRA-270): every port in
 * this codebase is required to have one, so {@see \App\Administration\Infrastructure\Security\PrivilegeVoter}'s
 * and {@see \App\Administration\Infrastructure\Security\UnionOfGrantSources}'s
 * unit tests stay #[Small], and so a new source (LRA-271's time-boxed
 * vendor support access) can be contract-tested without a database.
 *
 * Unlike {@see \App\Administration\Infrastructure\Persistence\Doctrine\Read\RankRoleGrants}
 * and {@see \App\Administration\Infrastructure\Persistence\Doctrine\Read\DirectRoleGrants},
 * this adapter has no backing aggregate to project from — grants are
 * seeded directly via {@see self::grant()}, matching how this codebase
 * treats a port with no natural in-memory backing store (e.g.
 * InMemoryStockMovementLedger).
 */
final class InMemoryPrivilegeGrantSource implements PrivilegeGrantSource
{
    /** @var array<string, PrivilegeGrants> */
    private array $grantsByAdministratorId = [];

    public function grant(
        AdministratorId $administratorId,
        Privilege $privilege,
        GrantOrigin $origin,
        string $sourceId,
        string $sourceName,
    ): void {
        $existing = $this->grantsByAdministratorId[$administratorId->value] ?? PrivilegeGrants::none();

        $this->grantsByAdministratorId[$administratorId->value] = $existing->merge(
            PrivilegeGrants::of(new PrivilegeGrant($privilege, $origin, $sourceId, $sourceName)),
        );
    }

    public function grantsFor(AdministratorId $id): PrivilegeGrants
    {
        return $this->grantsByAdministratorId[$id->value] ?? PrivilegeGrants::none();
    }
}
