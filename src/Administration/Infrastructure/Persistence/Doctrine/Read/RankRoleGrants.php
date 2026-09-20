<?php

declare(strict_types=1);

namespace App\Administration\Infrastructure\Persistence\Doctrine\Read;

use App\Administration\Domain\PrivilegeGrantSource;
use App\Administration\Domain\PrivilegeLookup;
use App\Administration\Domain\ValueObject\AdministratorId;
use App\Administration\Domain\ValueObject\GrantOrigin;
use App\Administration\Domain\ValueObject\PrivilegeGrants;
use Doctrine\DBAL\Connection;

/**
 * {@see PrivilegeGrantSource} adapter resolving privileges an
 * administrator holds through the roles their rank carries (LRA-270).
 * An administrator's rank can carry many roles (the legacy evidence has
 * Bellevue ranks carrying a mean of about seven and a maximum of
 * eighteen), so this aggregates across every role the rank grants, not
 * one.
 *
 * DBAL adapter rather than the EntityManager — this is a per-request
 * read on the authorization hot path, same CQRS-lite treatment as
 * {@see \App\Administration\Infrastructure\Persistence\Doctrine\Read\DoctrineRoleReadModel}.
 * Never checks whether the administrator is currently active: standing
 * is consulted once by {@see \App\Administration\Infrastructure\Security\UnionOfGrantSources}
 * before any source runs — see that class's docblock.
 *
 * A retired rank stops granting its roles' privileges (the
 * `administration_ranks.retired = false` join condition below), the
 * same immediate effect a retired role already has here: retirement
 * means the privileges it carried stop applying to whoever currently
 * holds it, not merely "not assignable to anyone new". An administrator
 * whose rank is retired without a replacement rank being set therefore
 * loses that rank's privileges starting with the next request, same as
 * a direct revocation — deliberately symmetric with the role-retirement
 * filter, not an oversight.
 */
final class RankRoleGrants implements PrivilegeGrantSource
{
    use MapsRoleRowsToPrivilegeGrants;

    public function __construct(
        private readonly Connection $connection,
        private readonly PrivilegeLookup $privilegeLookup,
    ) {
    }

    public function grantsFor(AdministratorId $id): PrivilegeGrants
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT r.id AS role_id, r.name AS role_name, r.privileges '
            . 'FROM administration_administrators a '
            . 'JOIN administration_ranks rk ON rk.id = a.rank_id '
            . 'JOIN administration_rank_roles rr ON rr.rank_id = a.rank_id '
            . 'JOIN administration_roles r ON r.id = rr.role_id '
            . 'WHERE a.id = :administratorId AND rk.retired = false AND r.retired = false',
            ['administratorId' => $id->value],
        );

        return $this->grantsFromRoleRows($rows, GrantOrigin::RankRole, $this->privilegeLookup);
    }
}
