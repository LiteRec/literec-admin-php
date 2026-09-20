<?php

declare(strict_types=1);

namespace App\Administration\Domain;

use App\Administration\Domain\Event\RoleDefined;
use App\Administration\Domain\Event\RolePrivilegeGranted;
use App\Administration\Domain\Event\RolePrivilegeRevoked;
use App\Administration\Domain\Event\RolePrivilegesReplaced;
use App\Administration\Domain\Event\RoleRenamed;
use App\Administration\Domain\Event\RoleReworded;
use App\Administration\Domain\Event\RoleRetired;
use App\Administration\Domain\Exception\RoleAlreadyRetired;
use App\Administration\Domain\ValueObject\Actor;
use App\Administration\Domain\ValueObject\PrivilegeSet;
use App\Administration\Domain\ValueObject\RoleDescription;
use App\Administration\Domain\ValueObject\RoleId;
use App\Administration\Domain\ValueObject\RoleName;
use DateTimeImmutable;
use Psr\Clock\ClockInterface;

/**
 * A named bundle of privileges — the grant model the new system
 * standardises on. Rank-to-role assignment (LRA-267) and
 * administrator-to-role assignment (LRA-269) are owned by the other
 * side of each relationship; no rank identity and no administrator
 * identity ever appears on this aggregate, keeping to one aggregate
 * per transaction.
 *
 * Pure domain class: no Symfony or Doctrine imports.
 */
final class Role
{
    use AggregateRoot;

    private RoleId $id;
    private RoleName $name;
    private RoleDescription $description;
    private PrivilegeSet $privileges;
    private bool $retired;
    private DateTimeImmutable $createdAt;
    private DateTimeImmutable $updatedAt;

    /**
     * Doctrine optimistic-lock version. Maintained by the ORM; never
     * mutated by domain code. Concurrent saves surface as
     * {@see \Doctrine\ORM\OptimisticLockException}, which
     * {@see \App\Administration\Infrastructure\Persistence\Doctrine\DoctrineRoles::save()}
     * translates into {@see \App\Administration\Domain\Exception\ConcurrentRoleModification}.
     */
    private int $version = 0;

    private function __construct()
    {
        // Intentionally empty: a Role is only ever built through the
        // named factory method, which populate every property. The
        // private constructor exists solely to forbid direct
        // instantiation.
    }

    public static function define(
        RoleId $id,
        RoleName $name,
        RoleDescription $description,
        PrivilegeSet $privileges,
        Actor $actor,
        ClockInterface $clock,
    ): self {
        $role = new self();
        $role->id = $id;
        $role->name = $name;
        $role->description = $description;
        $role->privileges = $privileges;
        $role->retired = false;
        $role->createdAt = $clock->now();
        $role->updatedAt = $role->createdAt;
        $role->recordThat(new RoleDefined($id, $name, $description, $privileges, $actor, $role->createdAt));

        return $role;
    }

    public function id(): RoleId
    {
        return $this->id;
    }

    public function name(): RoleName
    {
        return $this->name;
    }

    public function description(): RoleDescription
    {
        return $this->description;
    }

    public function privileges(): PrivilegeSet
    {
        return $this->privileges;
    }

    public function isRetired(): bool
    {
        return $this->retired;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
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
     * @throws RoleAlreadyRetired when this role is already retired.
     */
    public function rename(RoleName $name, Actor $actor, ClockInterface $clock): void
    {
        $this->guardNotRetired();

        // isIdenticalTo(), not equals() (LRA-280): equals() is
        // case-insensitive (it answers the uniqueness question), so a
        // case-only rename like "cashier" to "Cashier" must still
        // persist and record RoleRenamed rather than being silently
        // discarded as a no-op.
        if ($this->name->isIdenticalTo($name)) {
            return;
        }

        $this->name = $name;
        $this->updatedAt = $clock->now();
        $this->recordThat(new RoleRenamed($this->id, $name, $actor, $this->updatedAt));
    }

    /**
     * @throws RoleAlreadyRetired when this role is already retired.
     */
    public function reword(RoleDescription $description, Actor $actor, ClockInterface $clock): void
    {
        $this->guardNotRetired();

        if ($this->description->equals($description)) {
            return;
        }

        $this->description = $description;
        $this->updatedAt = $clock->now();
        $this->recordThat(new RoleReworded($this->id, $description, $actor, $this->updatedAt));
    }

    /**
     * @throws RoleAlreadyRetired when this role is already retired.
     */
    public function grant(Privilege $privilege, Actor $actor, ClockInterface $clock): void
    {
        $this->guardNotRetired();

        if ($this->privileges->contains($privilege)) {
            return;
        }

        $this->privileges = $this->privileges->with($privilege);
        $this->updatedAt = $clock->now();
        $this->recordThat(new RolePrivilegeGranted($this->id, $privilege, $actor, $this->updatedAt));
    }

    /**
     * @throws RoleAlreadyRetired when this role is already retired.
     */
    public function revoke(Privilege $privilege, Actor $actor, ClockInterface $clock): void
    {
        $this->guardNotRetired();

        if (!$this->privileges->contains($privilege)) {
            return;
        }

        $this->privileges = $this->privileges->without($privilege);
        $this->updatedAt = $clock->now();
        $this->recordThat(new RolePrivilegeRevoked($this->id, $privilege, $actor, $this->updatedAt));
    }

    /**
     * Replaces the whole privilege bundle in one write — what the edit
     * screen's bundle save posts, as distinct from the single-privilege
     * grant()/revoke() operations.
     *
     * @throws RoleAlreadyRetired when this role is already retired.
     */
    public function replacePrivileges(PrivilegeSet $privileges, Actor $actor, ClockInterface $clock): void
    {
        $this->guardNotRetired();

        if ($this->privileges->equals($privileges)) {
            return;
        }

        $before = $this->privileges;
        $this->privileges = $privileges;
        $this->updatedAt = $clock->now();
        $this->recordThat(new RolePrivilegesReplaced($this->id, $before, $privileges, $actor, $this->updatedAt));
    }

    /**
     * @throws RoleAlreadyRetired when this role is already retired.
     */
    public function retire(Actor $actor, ClockInterface $clock): void
    {
        $this->guardNotRetired();

        $this->retired = true;
        $this->updatedAt = $clock->now();
        $this->recordThat(new RoleRetired($this->id, $actor, $this->updatedAt));
    }

    /**
     * @throws RoleAlreadyRetired when this role is already retired.
     */
    private function guardNotRetired(): void
    {
        if ($this->retired) {
            throw RoleAlreadyRetired::for($this->id);
        }
    }
}
