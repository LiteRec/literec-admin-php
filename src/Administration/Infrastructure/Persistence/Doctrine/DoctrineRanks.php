<?php

declare(strict_types=1);

namespace App\Administration\Infrastructure\Persistence\Doctrine;

use App\Administration\Domain\Exception\ConcurrentRankModification;
use App\Administration\Domain\Exception\DuplicateRankName;
use App\Administration\Domain\Exception\RankNotFound;
use App\Administration\Domain\Rank;
use App\Administration\Domain\Ranks;
use App\Administration\Domain\ValueObject\ActorKind;
use App\Administration\Domain\ValueObject\AssignedRoles;
use App\Administration\Domain\ValueObject\RankId;
use App\Administration\Domain\ValueObject\RankName;
use App\Administration\Domain\ValueObject\RoleId;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\OptimisticLockException;
use Psr\Clock\ClockInterface;

/**
 * Doctrine adapter for the {@see Ranks} port. The only class under
 * src/Administration/ allowed to import {@see EntityManagerInterface}
 * (enforced by Deptrac).
 *
 * The assigned-role set is deliberately not an ORM-mapped field on
 * {@see Rank} (see Rank.orm.xml and Rank's own $roles docblock): it is
 * persisted to the administration_rank_roles join table, which this
 * class reconciles itself with plain DBAL statements inside the command
 * bus's transaction. byId()/byName()/listActiveBySeniority()/
 * listGrantingRole() all hydrate a Rank via Doctrine and then attach its
 * role set with one extra SELECT; add()/save() delete the rank's rows and
 * reinsert the current set, stamping assigned_at from the injected clock
 * and the assigned-by columns from the rank's most recently recorded
 * actor. At the ladder's scale (a few dozen ranks, a handful of roles
 * each) delete-and-reinsert is simpler and safer than diffing, and it
 * keeps both methods idempotent. The join row is only ever the *current*
 * holder of each assignment — LRA-273's audit log is the history of every
 * grant and revocation over time, not this table.
 */
final class DoctrineRanks implements Ranks
{
    private const string RANK_ROLES_TABLE = 'administration_rank_roles';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ClockInterface $clock,
    ) {
    }

    public function add(Rank $rank): void
    {
        try {
            $this->em->persist($rank);
            $this->em->flush();
        } catch (UniqueConstraintViolationException) {
            throw DuplicateRankName::of($rank->name()->value);
        }

        $this->syncAssignedRoles($rank);
    }

    public function save(Rank $rank): void
    {
        if (!$this->em->contains($rank)) {
            throw RankNotFound::withId($rank->id());
        }

        try {
            $this->em->flush();
        } catch (OptimisticLockException $e) {
            throw ConcurrentRankModification::for($rank->id()->value, $e);
        } catch (UniqueConstraintViolationException) {
            throw DuplicateRankName::of($rank->name()->value);
        }

        $this->syncAssignedRoles($rank);
    }

    public function byId(RankId $id): Rank
    {
        $rank = $this->em->find(Rank::class, $id);

        if (!$rank instanceof Rank) {
            throw RankNotFound::withId($id);
        }

        $rank->hydrateAssignedRoles($this->loadAssignedRoles($id));

        return $rank;
    }

    public function byName(RankName $name): Rank
    {
        $rank = $this->em->createQueryBuilder()
            ->select('r')
            ->from(Rank::class, 'r')
            ->where('r.name = :name')
            ->setParameter('name', $name->value)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$rank instanceof Rank) {
            throw RankNotFound::withName($name);
        }

        $rank->hydrateAssignedRoles($this->loadAssignedRoles($rank->id()));

        return $rank;
    }

    public function existsWithName(RankName $name): bool
    {
        $result = $this->em->createQueryBuilder()
            ->select('1')
            ->from(Rank::class, 'r')
            ->where('r.name = :name')
            ->setParameter('name', $name->value)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $result !== null;
    }

    public function listActiveBySeniority(int $offset, int $limit): array
    {
        /** @var list<Rank> $ranks */
        $ranks = $this->em->createQueryBuilder()
            ->select('r')
            ->from(Rank::class, 'r')
            ->where('r.retired = false')
            ->orderBy('r.seniority', 'ASC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        foreach ($ranks as $rank) {
            $rank->hydrateAssignedRoles($this->loadAssignedRoles($rank->id()));
        }

        return $ranks;
    }

    public function listGrantingRole(RoleId $roleId): array
    {
        /** @var list<string> $rankIds */
        $rankIds = $this->em->getConnection()->fetchFirstColumn(
            sprintf('SELECT rank_id FROM %s WHERE role_id = :roleId', self::RANK_ROLES_TABLE),
            ['roleId' => $roleId->value],
        );

        $ranks = [];
        foreach ($rankIds as $rankId) {
            $ranks[] = $this->byId(RankId::fromString($rankId));
        }

        return $ranks;
    }

    private function loadAssignedRoles(RankId $id): AssignedRoles
    {
        /** @var list<string> $roleIds */
        $roleIds = $this->em->getConnection()->fetchFirstColumn(
            sprintf('SELECT role_id FROM %s WHERE rank_id = :rankId', self::RANK_ROLES_TABLE),
            ['rankId' => $id->value],
        );

        return AssignedRoles::of(...array_map(
            static fn (string $roleId): RoleId => RoleId::fromString($roleId),
            $roleIds,
        ));
    }

    private function syncAssignedRoles(Rank $rank): void
    {
        $actor = $rank->lastPendingActor();

        if ($actor === null) {
            // No mutation was actually recorded on this call — e.g. a
            // rename()/changeSeniority() no-op when the value was
            // already current. The assigned-role set has not changed,
            // so the join table needs no rewrite.
            return;
        }

        $connection = $this->em->getConnection();
        $connection->executeStatement(
            sprintf('DELETE FROM %s WHERE rank_id = :rankId', self::RANK_ROLES_TABLE),
            ['rankId' => $rank->id()->value],
        );

        $roleIds = $rank->roles()->toList();

        if ($roleIds === []) {
            return;
        }

        $assignedAt = $this->clock->now();

        foreach ($roleIds as $roleId) {
            $connection->executeStatement(
                sprintf(
                    'INSERT INTO %s '
                    . '(rank_id, role_id, assigned_at, assigned_by_kind, assigned_by_administrator_id, '
                    . 'assigned_by_sign_in_account_id) '
                    . 'VALUES (:rankId, :roleId, :assignedAt, :kind, :administratorId, :signInAccountId)',
                    self::RANK_ROLES_TABLE,
                ),
                [
                    'rankId' => $rank->id()->value,
                    'roleId' => $roleId->value,
                    'assignedAt' => $assignedAt->format('Y-m-d H:i:s'),
                    'kind' => $this->columnKindFor($actor->kind),
                    'administratorId' => $actor->administratorId?->value,
                    'signInAccountId' => $actor->signInAccountId?->value,
                ],
            );
        }
    }

    /**
     * Translates {@see ActorKind} into the join table's
     * assigned_by_kind values ('administrator' / 'sign_in_account' /
     * 'system'), matching the migration's CHECK constraint — distinct
     * from ActorKind's own backing values, which are the primitive form
     * a command DTO carries (e.g. 'ADMINISTRATOR').
     */
    private function columnKindFor(ActorKind $kind): string
    {
        return match ($kind) {
            ActorKind::Administrator => 'administrator',
            ActorKind::SignInAccount => 'sign_in_account',
            ActorKind::System => 'system',
        };
    }
}
