<?php

declare(strict_types=1);

namespace App\Tests\Support\Trait;

use App\Administration\Domain\Exception\DuplicateRankName;
use App\Administration\Domain\Exception\RankNotFound;
use App\Administration\Domain\Rank;
use App\Administration\Domain\Ranks;
use App\Administration\Domain\ValueObject\Actor;
use App\Administration\Domain\ValueObject\AssignedRoles;
use App\Administration\Domain\ValueObject\RankId;
use App\Administration\Domain\ValueObject\RankName;
use App\Administration\Domain\ValueObject\RoleId;
use App\Administration\Domain\ValueObject\SeniorityLevel;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Component\Clock\MockClock;

/**
 * Shared behavioural contract for any {@see Ranks} adapter. The InMemory
 * and Doctrine drivers both pin to this trait so the implementations
 * cannot drift — same treatment as {@see RolesContractCases}.
 */
trait RanksContractCases
{
    private const string RANK_A = '019571bf-5d51-7000-b500-00000000fa01';
    private const string RANK_B = '019571bf-5d51-7000-b500-00000000fa02';
    private const string ROLE_A = '019571bf-5d51-7000-b500-00000000fb01';
    private const string ROLE_B = '019571bf-5d51-7000-b500-00000000fb02';
    private const string RANK_NAME_DIRECTOR = 'Director';
    private const string RANK_NAME_MANAGER = 'Manager';

    abstract protected function ranks(): Ranks;

    abstract protected function clock(): MockClock;

    /**
     * Adapter-specific hook run between a write and the read that
     * verifies it, so the assertion exercises a genuine round trip
     * rather than Doctrine's identity map handing back the same
     * in-memory object it was given. A no-op for InMemoryRanks, which
     * has no identity map to reset; the Doctrine adapter clears its
     * EntityManager.
     */
    abstract protected function resetPersistenceContext(): void;

    #[Test]
    #[TestDox('add() then byId() round-trips name, seniority, and retired.')]
    public function add_then_by_id_round_trips(): void
    {
        $this->seedRank(self::RANK_A, self::RANK_NAME_DIRECTOR, 20);
        $this->resetPersistenceContext();

        $loaded = $this->ranks()->byId(RankId::fromString(self::RANK_A));
        self::assertSame(self::RANK_NAME_DIRECTOR, $loaded->name()->value);
        self::assertTrue($loaded->seniority()->equals(SeniorityLevel::of(20)));
        self::assertFalse($loaded->isRetired());
    }

    #[Test]
    #[TestDox('byId() throws RankNotFound when no rank has that id.')]
    public function by_id_throws_when_missing(): void
    {
        $this->expectException(RankNotFound::class);
        $this->ranks()->byId(RankId::fromString(self::RANK_A));
    }

    #[Test]
    #[TestDox('byName() returns the matching rank.')]
    public function by_name_returns_matching_rank(): void
    {
        $this->seedRank(self::RANK_A, self::RANK_NAME_MANAGER, 30);
        $this->resetPersistenceContext();

        $loaded = $this->ranks()->byName(RankName::of(self::RANK_NAME_MANAGER));
        self::assertSame(self::RANK_A, $loaded->id()->value);
    }

    #[Test]
    #[TestDox('byName() throws RankNotFound when no rank matches.')]
    public function by_name_throws_when_missing(): void
    {
        $this->expectException(RankNotFound::class);
        $this->ranks()->byName(RankName::of('Nonexistent'));
    }

    #[Test]
    #[TestDox('existsWithName() reports whether a rank with that name has been added.')]
    public function exists_with_name_reports_membership(): void
    {
        $this->seedRank(self::RANK_A, self::RANK_NAME_DIRECTOR, 20);

        self::assertTrue($this->ranks()->existsWithName(RankName::of(self::RANK_NAME_DIRECTOR)));
        self::assertFalse($this->ranks()->existsWithName(RankName::of(self::RANK_NAME_MANAGER)));
    }

    #[Test]
    #[TestDox('add() throws DuplicateRankName when another rank already has that name.')]
    public function add_throws_on_duplicate_name(): void
    {
        $this->seedRank(self::RANK_A, self::RANK_NAME_DIRECTOR, 20);

        $this->expectException(DuplicateRankName::class);
        $this->addRankNamed(self::RANK_B, self::RANK_NAME_DIRECTOR, 30);
    }

    #[Test]
    #[TestDox('save() throws DuplicateRankName when renaming into another rank\'s name.')]
    public function save_throws_when_renamed_into_another_name(): void
    {
        $this->seedRank(self::RANK_A, self::RANK_NAME_DIRECTOR, 20);
        $this->seedRank(self::RANK_B, self::RANK_NAME_MANAGER, 30);

        $second = $this->ranks()->byId(RankId::fromString(self::RANK_B));
        $second->rename(RankName::of(self::RANK_NAME_DIRECTOR), Actor::system(), $this->clock());

        $this->expectException(DuplicateRankName::class);
        $this->ranks()->save($second);
    }

    #[Test]
    #[TestDox('save() persists rename and seniority-change mutations across reloads.')]
    public function save_persists_mutations(): void
    {
        $this->seedRank(self::RANK_A, self::RANK_NAME_DIRECTOR, 20);

        $loaded = $this->ranks()->byId(RankId::fromString(self::RANK_A));
        $loaded->rename(RankName::of('Renamed'), Actor::system(), $this->clock());
        $this->ranks()->save($loaded);
        $this->resetPersistenceContext();

        $reloaded = $this->ranks()->byId(RankId::fromString(self::RANK_A));
        $reloaded->changeSeniority(SeniorityLevel::of(40), Actor::system(), $this->clock());
        $this->ranks()->save($reloaded);
        $this->resetPersistenceContext();

        $final = $this->ranks()->byId(RankId::fromString(self::RANK_A));
        self::assertSame('Renamed', $final->name()->value);
        self::assertTrue($final->seniority()->equals(SeniorityLevel::of(40)));
    }

    #[Test]
    #[TestDox('save() throws RankNotFound when the rank was never added.')]
    public function save_throws_when_not_added(): void
    {
        $rank = Rank::define(
            RankId::fromString(self::RANK_A),
            RankName::of('Never Added'),
            SeniorityLevel::of(50),
            AssignedRoles::none(),
            Actor::system(),
            $this->clock(),
        );

        $this->expectException(RankNotFound::class);
        $this->ranks()->save($rank);
    }

    #[Test]
    #[TestDox('listActiveBySeniority() excludes retired ranks and orders ascending (most senior first).')]
    public function list_active_by_seniority_excludes_retired_and_orders_ascending(): void
    {
        $this->seedRank(self::RANK_A, 'Less Senior', 80);
        $this->seedRank(self::RANK_B, 'More Senior', 10);
        $retired = $this->seedRank('019571bf-5d51-7000-b500-00000000fa03', 'Retired', 5);
        $retired->retire(Actor::system(), $this->clock());
        $this->ranks()->save($retired);
        $this->resetPersistenceContext();

        $active = $this->ranks()->listActiveBySeniority(0, 10);
        $ids = array_map(static fn (Rank $rank): string => $rank->id()->value, $active);

        self::assertSame([self::RANK_B, self::RANK_A], $ids);
    }

    #[Test]
    #[TestDox('Round-tripping a rank\'s assigned roles through the adapter preserves the set.')]
    public function assigned_roles_round_trip_through_the_adapter(): void
    {
        $this->seedRank(self::RANK_A, self::RANK_NAME_DIRECTOR, 20);

        $rank = $this->ranks()->byId(RankId::fromString(self::RANK_A));
        $rank->grantRole(RoleId::fromString(self::ROLE_A), Actor::system(), $this->clock());
        $this->ranks()->save($rank);
        $this->resetPersistenceContext();

        $reloaded = $this->ranks()->byId(RankId::fromString(self::RANK_A));
        $reloaded->grantRole(RoleId::fromString(self::ROLE_B), Actor::system(), $this->clock());
        $this->ranks()->save($reloaded);
        $this->resetPersistenceContext();

        $final = $this->ranks()->byId(RankId::fromString(self::RANK_A));
        self::assertTrue($final->roles()->equals(
            AssignedRoles::of(RoleId::fromString(self::ROLE_A), RoleId::fromString(self::ROLE_B)),
        ));
    }

    #[Test]
    #[TestDox('Revoking a role and reloading no longer includes it in the assigned set.')]
    public function revoked_roles_do_not_survive_a_reload(): void
    {
        $this->seedRank(
            self::RANK_A,
            self::RANK_NAME_DIRECTOR,
            20,
            AssignedRoles::of(RoleId::fromString(self::ROLE_A)),
        );

        $rank = $this->ranks()->byId(RankId::fromString(self::RANK_A));
        $rank->revokeRole(RoleId::fromString(self::ROLE_A), Actor::system(), $this->clock());
        $this->ranks()->save($rank);
        $this->resetPersistenceContext();

        $reloaded = $this->ranks()->byId(RankId::fromString(self::RANK_A));
        self::assertSame(0, $reloaded->roles()->count());
    }

    #[Test]
    #[TestDox('listGrantingRole() answers "which ranks grant this role" via the join table.')]
    public function list_granting_role_finds_every_rank_with_that_role(): void
    {
        $roleId = RoleId::fromString(self::ROLE_A);
        $this->seedRank(self::RANK_A, self::RANK_NAME_DIRECTOR, 20, AssignedRoles::of($roleId));
        $this->seedRank(self::RANK_B, self::RANK_NAME_MANAGER, 30, AssignedRoles::none());
        $this->resetPersistenceContext();

        $granting = $this->ranks()->listGrantingRole($roleId);
        $ids = array_map(static fn (Rank $rank): string => $rank->id()->value, $granting);

        self::assertSame([self::RANK_A], $ids);
    }

    /**
     * Deliberately does not call {@see self::resetPersistenceContext()}:
     * some callers mutate and save() the returned Rank directly, and
     * Doctrine's save() requires the instance it is given to still be
     * managed. Callers that need a genuine round trip reset explicitly,
     * between this call and the verifying read.
     */
    private function addRankNamed(string $id, string $name, int $seniority): void
    {
        $rank = Rank::define(
            RankId::fromString($id),
            RankName::of($name),
            SeniorityLevel::of($seniority),
            AssignedRoles::none(),
            Actor::system(),
            $this->clock(),
        );
        $this->ranks()->add($rank);
    }

    /**
     * Deliberately does not call {@see self::resetPersistenceContext()} —
     * see {@see self::addRankNamed()}.
     */
    private function seedRank(string $id, string $name, int $seniority, ?AssignedRoles $roles = null): Rank
    {
        $rank = Rank::define(
            RankId::fromString($id),
            RankName::of($name),
            SeniorityLevel::of($seniority),
            $roles ?? AssignedRoles::none(),
            Actor::system(),
            $this->clock(),
        );
        $this->ranks()->add($rank);

        return $rank;
    }
}
