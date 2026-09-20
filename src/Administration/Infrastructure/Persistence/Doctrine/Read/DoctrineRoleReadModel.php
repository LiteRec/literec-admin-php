<?php

declare(strict_types=1);

namespace App\Administration\Infrastructure\Persistence\Doctrine\Read;

use App\Administration\Application\Query\Port\RoleReadModel;
use App\Administration\Application\Query\View\RoleDetailView;
use App\Administration\Application\Query\View\RolePrivilegeView;
use App\Administration\Application\Query\View\RoleSummaryView;
use App\Administration\Domain\Exception\RoleNotFound;
use App\Administration\Domain\Privilege;
use App\Administration\Domain\ValueObject\RoleId;
use App\Shared\Infrastructure\Doctrine\Read\RowFieldExtraction;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

/**
 * Doctrine DBAL adapter for the {@see RoleReadModel} port. Read side
 * only — uses {@see Connection} (NOT the EntityManager) so read paths
 * never pay the hydrator/UnitOfWork cost; projects straight from
 * administration_roles into the view DTOs. Queries hit the same table
 * that {@see \App\Administration\Infrastructure\Persistence\Doctrine\DoctrineRoles}
 * writes; CQRS-lite.
 */
final class DoctrineRoleReadModel implements RoleReadModel
{
    use RowFieldExtraction;

    public function __construct(private readonly Connection $connection)
    {
    }

    public function listRoles(bool $includeRetired): array
    {
        $sql = 'SELECT id, name, privileges, retired FROM administration_roles';
        $params = [];
        $types = [];

        if (!$includeRetired) {
            $sql .= ' WHERE retired = :retired';
            // Bound explicitly as ParameterType::BOOLEAN: DBAL's default
            // parameter binding casts a bare PHP `false` to an empty
            // string, which Postgres then rejects as an invalid boolean
            // literal.
            $params['retired'] = false;
            $types['retired'] = ParameterType::BOOLEAN;
        }

        $sql .= ' ORDER BY name ASC';

        $rows = $this->connection->fetchAllAssociative($sql, $params, $types);

        return array_map(
            fn (array $row): RoleSummaryView => new RoleSummaryView(
                $this->rowString($row, 'id'),
                $this->rowString($row, 'name'),
                count($this->decodePrivilegeNames($row)),
                $this->rowBool($row, 'retired'),
            ),
            $rows,
        );
    }

    public function roleDetail(RoleId $id): RoleDetailView
    {
        $row = $this->connection->fetchAssociative(
            'SELECT id, name, description, privileges, retired FROM administration_roles WHERE id = :id',
            ['id' => $id->value],
        );

        if ($row === false) {
            throw RoleNotFound::withId($id);
        }

        return new RoleDetailView(
            $this->rowString($row, 'id'),
            $this->rowString($row, 'name'),
            $this->rowString($row, 'description'),
            $this->privilegeViewsFrom($row),
            $this->rowBool($row, 'retired'),
        );
    }

    /**
     * @param array<string, mixed> $row
     * @return list<string>
     */
    private function decodePrivilegeNames(array $row): array
    {
        $raw = $row['privileges'] ?? null;
        $decoded = is_string($raw) ? json_decode($raw, true) : null;

        if (!is_array($decoded)) {
            return [];
        }

        return array_values(array_filter($decoded, is_string(...)));
    }

    /**
     * @param array<string, mixed> $row
     * @return list<RolePrivilegeView>
     */
    private function privilegeViewsFrom(array $row): array
    {
        $views = [];
        foreach ($this->decodePrivilegeNames($row) as $name) {
            $privilege = Privilege::tryFrom($name);

            // A stored name with no matching case (a retired enum case)
            // is dropped rather than raised — same fail-closed contract
            // PrivilegeSetType applies on the write-side hydration path.
            if ($privilege !== null) {
                $views[] = new RolePrivilegeView($privilege->value, $privilege->definition()->displayName);
            }
        }

        return $views;
    }
}
