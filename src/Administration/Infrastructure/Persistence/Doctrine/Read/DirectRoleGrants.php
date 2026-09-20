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
 * administrator holds through roles assigned directly to them, bypassing
 * their rank (LRA-270) — the case LRA-268 describes where a rank is too
 * coarse for one administrator's actual duties. An administrator can
 * hold many directly assigned roles, so this aggregates across all of
 * them, mirroring {@see RankRoleGrants}.
 *
 * DBAL adapter rather than the EntityManager — same CQRS-lite treatment
 * as {@see RankRoleGrants}. Never checks whether the administrator is
 * currently active — see that class's docblock for why.
 */
final class DirectRoleGrants implements PrivilegeGrantSource
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
            . 'FROM administration_administrator_roles ar '
            . 'JOIN administration_roles r ON r.id = ar.role_id '
            . 'WHERE ar.administrator_id = :administratorId AND r.retired = false',
            ['administratorId' => $id->value],
        );

        return $this->grantsFromRoleRows($rows, GrantOrigin::DirectRole, $this->privilegeLookup);
    }
}
