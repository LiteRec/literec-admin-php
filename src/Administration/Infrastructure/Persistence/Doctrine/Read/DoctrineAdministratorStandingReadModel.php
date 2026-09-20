<?php

declare(strict_types=1);

namespace App\Administration\Infrastructure\Persistence\Doctrine\Read;

use App\Administration\Application\Query\Port\AdministratorStandingReadModel;
use App\Administration\Application\Query\View\AdministratorStandingView;
use App\Administration\Domain\ValueObject\AdministratorId;
use App\Administration\Domain\ValueObject\SignInAccountId;
use App\Shared\Infrastructure\Doctrine\Read\RowFieldExtraction;
use Doctrine\DBAL\Connection;

/**
 * Doctrine DBAL adapter for the {@see AdministratorStandingReadModel}
 * port. Read side only — uses {@see Connection} (NOT the EntityManager)
 * so this per-request read never pays the hydrator/UnitOfWork cost;
 * projects straight from administration_administrators and
 * administration_administrator_roles. Queries the same tables
 * {@see \App\Administration\Infrastructure\Persistence\Doctrine\DoctrineAdministrators}
 * writes; CQRS-lite, same pattern as DoctrineRoleReadModel.
 *
 * Does not join administration_tenures: `standing` is already a
 * denormalised column on the administrator row (see Administrator's own
 * docblock on why), so re-deriving it from the open/closed tenure history
 * on every request would be redundant work for no additional information
 * this view actually carries.
 */
final class DoctrineAdministratorStandingReadModel implements AdministratorStandingReadModel
{
    use RowFieldExtraction;

    public function __construct(private readonly Connection $connection)
    {
    }

    public function standingFor(SignInAccountId $signInAccountId): ?AdministratorStandingView
    {
        $rows = $this->connection->fetchAllAssociative(
            self::baseQuery() . 'WHERE a.sign_in_account_id = :value',
            ['value' => $signInAccountId->value],
        );

        return $this->viewFromRows($rows);
    }

    public function standingOfAdministrator(AdministratorId $administratorId): ?AdministratorStandingView
    {
        $rows = $this->connection->fetchAllAssociative(
            self::baseQuery() . 'WHERE a.id = :value',
            ['value' => $administratorId->value],
        );

        return $this->viewFromRows($rows);
    }

    private static function baseQuery(): string
    {
        return 'SELECT a.id, a.rank_id, a.standing, ar.role_id '
            . 'FROM administration_administrators a '
            . 'LEFT JOIN administration_administrator_roles ar ON ar.administrator_id = a.id ';
    }

    /**
     * Shared row-to-view projection behind both finders — they differ
     * only in which column the administrator row is matched by.
     *
     * @param list<array<string, mixed>> $rows
     */
    private function viewFromRows(array $rows): ?AdministratorStandingView
    {
        if ($rows === []) {
            return null;
        }

        $roleIds = array_values(array_filter(array_map(
            fn (array $row): ?string => $this->rowNullableString($row, 'role_id'),
            $rows,
        )));

        return new AdministratorStandingView(
            $this->rowString($rows[0], 'id'),
            $this->rowString($rows[0], 'standing'),
            $this->rowString($rows[0], 'rank_id'),
            $roleIds,
        );
    }
}
