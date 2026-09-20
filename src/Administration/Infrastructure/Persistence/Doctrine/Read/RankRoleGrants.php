<?php

declare(strict_types=1);

namespace App\Administration\Infrastructure\Persistence\Doctrine\Read;

use App\Administration\Domain\PrivilegeGrantSource;
use App\Administration\Domain\PrivilegeLookup;
use App\Administration\Domain\ValueObject\AdministratorId;
use App\Administration\Domain\ValueObject\GrantOrigin;
use App\Administration\Domain\ValueObject\PrivilegeGrant;
use App\Administration\Domain\ValueObject\PrivilegeGrants;
use App\Shared\Infrastructure\Doctrine\Read\RowFieldExtraction;
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
 */
final class RankRoleGrants implements PrivilegeGrantSource
{
    use RowFieldExtraction;
    use DecodesStoredPrivilegeNames;

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
            . 'JOIN administration_rank_roles rr ON rr.rank_id = a.rank_id '
            . 'JOIN administration_roles r ON r.id = rr.role_id '
            . 'WHERE a.id = :administratorId AND r.retired = false',
            ['administratorId' => $id->value],
        );

        $grants = [];
        foreach ($rows as $row) {
            $roleId = $this->rowString($row, 'role_id');
            $roleName = $this->rowString($row, 'role_name');

            foreach ($this->decodeGrantedPrivileges($row, 'privileges', $this->privilegeLookup) as $privilege) {
                $grants[] = new PrivilegeGrant($privilege, GrantOrigin::RankRole, $roleId, $roleName);
            }
        }

        return PrivilegeGrants::of(...$grants);
    }
}
