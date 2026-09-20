<?php

declare(strict_types=1);

namespace App\Administration\Infrastructure\Persistence\Doctrine;

use App\Administration\Domain\Event\RankDefined;
use App\Administration\Domain\Event\RoleGrantedToRank;
use App\Administration\Domain\Event\RoleRevokedFromRank;
use App\Administration\Domain\Exception\ConcurrentRankModification;
use App\Administration\Domain\Exception\DuplicateRankName;
use App\Administration\Domain\Exception\RankNotFound;
use App\Administration\Domain\Rank;
use App\Administration\Domain\Ranks;
use App\Administration\Domain\ValueObject\Actor;
use App\Administration\Domain\ValueObject\ActorKind;
use App\Administration\Domain\ValueObject\AssignedRoles;
use App\Administration\Domain\ValueObject\RankId;
use App\Administration\Domain\ValueObject\RankName;
use App\Administration\Domain\ValueObject\RoleId;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\OptimisticLockException;

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
 * role set with one extra SELECT.
 *
 * add()/save() apply only the role-assignment events actually pending on
 * the aggregate — a RankDefined with an initial role set inserts those
 * rows, a RoleGrantedToRank/RoleRevokedFromRank inserts/deletes the one
 * row it names, and every other event (RankRenamed, RankSeniorityChanged,
 * RankRetired, RankReinstated) leaves this table untouched. Each row is
 * stamped with *that event's own* actor and occurredAt, not whichever
 * event happened to be recorded most recently on the aggregate — an
 * unrelated rename must never rewrite another role's assignment
 * metadata. The join row is still only ever the *current* holder of each
 * assignment; LRA-273's audit log is the history of every grant and
 * revocation over time, not this table.
 */
final class DoctrineRanks implements Ranks
{
    private const string RANK_ROLES_TABLE = 'administration_rank_roles';

    public function __construct(
        private readonly EntityManagerInterface $em,
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

        $this->applyRoleAssignmentEvents($rank);
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

        $this->applyRoleAssignmentEvents($rank);
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

    /**
     * Walks the events still pending on $rank (not yet released for
     * dispatch) and applies only the ones that touch role assignment,
     * each stamped with its own actor and occurredAt. Every other event
     * type is ignored — a RankRenamed/RankSeniorityChanged/RankRetired/
     * RankReinstated never reaches the join table.
     */
    private function applyRoleAssignmentEvents(Rank $rank): void
    {
        $connection = $this->em->getConnection();

        foreach ($rank->pendingEvents() as $event) {
            match (true) {
                $event instanceof RankDefined => $this->insertInitialRoles($connection, $event),
                $event instanceof RoleGrantedToRank => $this->insertRoleAssignment(
                    $connection,
                    $event->rankId,
                    $event->roleId,
                    $event->actor,
                    $event->occurredAt,
                ),
                $event instanceof RoleRevokedFromRank => $connection->executeStatement(
                    sprintf('DELETE FROM %s WHERE rank_id = :rankId AND role_id = :roleId', self::RANK_ROLES_TABLE),
                    ['rankId' => $event->rankId->value, 'roleId' => $event->roleId->value],
                ),
                default => null,
            };
        }
    }

    private function insertInitialRoles(Connection $connection, RankDefined $event): void
    {
        foreach ($event->roles->toList() as $roleId) {
            $this->insertRoleAssignment($connection, $event->rankId, $roleId, $event->actor, $event->occurredAt);
        }
    }

    private function insertRoleAssignment(
        Connection $connection,
        RankId $rankId,
        RoleId $roleId,
        Actor $actor,
        DateTimeImmutable $assignedAt,
    ): void {
        $connection->executeStatement(
            sprintf(
                'INSERT INTO %s '
                . '(rank_id, role_id, assigned_at, assigned_by_kind, assigned_by_administrator_id, '
                . 'assigned_by_sign_in_account_id) '
                . 'VALUES (:rankId, :roleId, :assignedAt, :kind, :administratorId, :signInAccountId)',
                self::RANK_ROLES_TABLE,
            ),
            [
                'rankId' => $rankId->value,
                'roleId' => $roleId->value,
                'assignedAt' => $assignedAt->format('Y-m-d H:i:s'),
                'kind' => $this->columnKindFor($actor->kind),
                'administratorId' => $actor->administratorId?->value,
                'signInAccountId' => $actor->signInAccountId?->value,
            ],
        );
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
