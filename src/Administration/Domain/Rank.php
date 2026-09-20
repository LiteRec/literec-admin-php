<?php

declare(strict_types=1);

namespace App\Administration\Domain;

use App\Administration\Domain\Event\RankDefined;
use App\Administration\Domain\Event\RankReinstated;
use App\Administration\Domain\Event\RankRenamed;
use App\Administration\Domain\Event\RankRetired;
use App\Administration\Domain\Event\RankSeniorityChanged;
use App\Administration\Domain\Event\RoleGrantedToRank;
use App\Administration\Domain\Event\RoleRevokedFromRank;
use App\Administration\Domain\Exception\RankIsRetired;
use App\Administration\Domain\ValueObject\Actor;
use App\Administration\Domain\ValueObject\AssignedRoles;
use App\Administration\Domain\ValueObject\RankId;
use App\Administration\Domain\ValueObject\RankName;
use App\Administration\Domain\ValueObject\RoleId;
use App\Administration\Domain\ValueObject\SeniorityLevel;
use DateTimeImmutable;
use Psr\Clock\ClockInterface;

/**
 * Where an administrator sits, separated from what they may do. A Rank
 * carries a {@see SeniorityLevel} and a set of granted {@see RoleId}s; it
 * never carries a {@see Privilege} directly (AC 2 — enforced by
 * {@see \App\Tests\Architecture\RanksDoNotReferencePrivilegesRule}) and it
 * never carries an administrator identity — that reference runs the other
 * way, from Administrator to Rank (LRA-269).
 *
 * The rank-to-role link lives here, not on {@see Role}: Role references
 * neither a rank nor an administrator, so this aggregate owns
 * grantRole()/revokeRole(). The granted set is in-memory only —
 * {@see \App\Administration\Infrastructure\Persistence\Doctrine\DoctrineRanks}
 * persists it to a join table rather than a mapped column, so this class
 * has no Doctrine or Symfony import despite that persistence detail.
 *
 * Pure domain class: no Symfony or Doctrine imports.
 */
final class Rank
{
    use AggregateRoot;

    private RankId $id;
    private RankName $name;
    private SeniorityLevel $seniority;

    /**
     * Not an ORM-mapped field: {@see \App\Administration\Infrastructure\Persistence\Doctrine\DoctrineRanks}
     * reconciles it against the rank-roles join table and attaches the
     * loaded value via {@see hydrateAssignedRoles()} immediately after
     * fetching the row. A freshly defined Rank has it set directly by
     * {@see define()} instead, so it is never left uninitialised on any
     * instance handed back to a caller.
     */
    private AssignedRoles $roles;

    private bool $retired;
    private DateTimeImmutable $definedAt;
    private DateTimeImmutable $updatedAt;

    /**
     * Doctrine optimistic-lock version. Maintained by the ORM; never
     * mutated by domain code. Concurrent saves surface as
     * {@see \Doctrine\ORM\OptimisticLockException}, which
     * {@see \App\Administration\Infrastructure\Persistence\Doctrine\DoctrineRanks::save()}
     * translates into {@see \App\Administration\Domain\Exception\ConcurrentRankModification}.
     */
    private int $version = 0;

    private function __construct()
    {
        // Intentionally empty: a Rank is only ever built through the
        // named factory method, which populates every property. The
        // private constructor exists solely to forbid direct
        // instantiation.
    }

    public static function define(
        RankId $id,
        RankName $name,
        SeniorityLevel $seniority,
        AssignedRoles $roles,
        Actor $actor,
        ClockInterface $clock,
    ): self {
        $rank = new self();
        $rank->id = $id;
        $rank->name = $name;
        $rank->seniority = $seniority;
        $rank->roles = $roles;
        $rank->retired = false;
        $rank->definedAt = $clock->now();
        $rank->updatedAt = $rank->definedAt;
        $rank->recordThat(new RankDefined($id, $name, $seniority, $roles, $actor, $rank->definedAt));

        return $rank;
    }

    public function id(): RankId
    {
        return $this->id;
    }

    public function name(): RankName
    {
        return $this->name;
    }

    public function seniority(): SeniorityLevel
    {
        return $this->seniority;
    }

    public function roles(): AssignedRoles
    {
        return $this->roles;
    }

    public function isRetired(): bool
    {
        return $this->retired;
    }

    public function definedAt(): DateTimeImmutable
    {
        return $this->definedAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function version(): int
    {
        return $this->version;
    }

    /**
     * @throws RankIsRetired when this rank is already retired.
     */
    public function rename(RankName $name, Actor $actor, ClockInterface $clock): void
    {
        $this->guardNotRetired();

        // isIdenticalTo(), not equals(): equals() is case-insensitive
        // (it answers the uniqueness question), so a case-only rename
        // like "director" to "Director" must still persist and record
        // RankRenamed rather than being silently discarded as a no-op.
        if ($this->name->isIdenticalTo($name)) {
            return;
        }

        $this->name = $name;
        $this->updatedAt = $clock->now();
        $this->recordThat(new RankRenamed($this->id, $name, $actor, $this->updatedAt));
    }

    /**
     * @throws RankIsRetired when this rank is already retired.
     */
    public function changeSeniority(SeniorityLevel $seniority, Actor $actor, ClockInterface $clock): void
    {
        $this->guardNotRetired();

        if ($this->seniority->equals($seniority)) {
            return;
        }

        $this->seniority = $seniority;
        $this->updatedAt = $clock->now();
        $this->recordThat(new RankSeniorityChanged($this->id, $seniority, $actor, $this->updatedAt));
    }

    /**
     * @throws RankIsRetired when this rank is already retired.
     */
    public function grantRole(RoleId $roleId, Actor $actor, ClockInterface $clock): void
    {
        $this->guardNotRetired();

        if ($this->roles->contains($roleId)) {
            return;
        }

        $this->roles = $this->roles->with($roleId);
        $this->updatedAt = $clock->now();
        $this->recordThat(new RoleGrantedToRank($this->id, $roleId, $actor, $this->updatedAt));
    }

    /**
     * @throws RankIsRetired when this rank is already retired.
     */
    public function revokeRole(RoleId $roleId, Actor $actor, ClockInterface $clock): void
    {
        $this->guardNotRetired();

        if (!$this->roles->contains($roleId)) {
            return;
        }

        $this->roles = $this->roles->without($roleId);
        $this->updatedAt = $clock->now();
        $this->recordThat(new RoleRevokedFromRank($this->id, $roleId, $actor, $this->updatedAt));
    }

    /**
     * @throws RankIsRetired when this rank is already retired.
     */
    public function retire(Actor $actor, ClockInterface $clock): void
    {
        $this->guardNotRetired();

        $this->retired = true;
        $this->updatedAt = $clock->now();
        $this->recordThat(new RankRetired($this->id, $actor, $this->updatedAt));
    }

    /**
     * The inverse of retire(). A no-op (records no event) when the rank is
     * already active — unlike retire(), which throws on the equivalent
     * already-in-that-state call, since "reinstate an active rank" has no
     * sensible active-state guard to enforce.
     *
     * Reachable via {@see \App\Administration\Application\Command\ReinstateRankHandler}:
     * a public domain method with no caller would ship as dead code with
     * no way to tell whether reinstatement is unsupported, unfinished,
     * or wired somewhere unexpected — and without it, retirement would
     * be one-way, making a mistaken retirement unfixable short of a
     * database edit. This makes it a seventh write use case for
     * LRA-267, alongside the six the ticket's Implementation Plan
     * originally named.
     */
    public function reinstate(Actor $actor, ClockInterface $clock): void
    {
        if (!$this->retired) {
            return;
        }

        $this->retired = false;
        $this->updatedAt = $clock->now();
        $this->recordThat(new RankReinstated($this->id, $actor, $this->updatedAt));
    }

    /**
     * Reconstitutes the in-memory role set after infrastructure loads it
     * from the rank-roles join table. Not a general setter: it exists
     * only because that join table is deliberately not an ORM-mapped
     * field (see this class's $roles docblock), so infrastructure must
     * attach it explicitly instead of Doctrine populating it via
     * reflection the way every mapped field is populated. Never called
     * outside {@see \App\Administration\Infrastructure\Persistence\Doctrine\DoctrineRanks}.
     */
    public function hydrateAssignedRoles(AssignedRoles $roles): void
    {
        $this->roles = $roles;
    }

    /**
     * @throws RankIsRetired when this rank is already retired.
     */
    private function guardNotRetired(): void
    {
        if ($this->retired) {
            throw RankIsRetired::for($this->id);
        }
    }
}
