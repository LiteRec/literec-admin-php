<?php

declare(strict_types=1);

namespace App\Administration\Infrastructure\Persistence\InMemory;

use App\Administration\Domain\Administrator;
use App\Administration\Domain\Administrators;
use App\Administration\Domain\Exception\AdministratorAlreadyExists;
use App\Administration\Domain\Exception\AdministratorNotFound;
use App\Administration\Domain\Exception\SignInAccountAlreadyAnAdministrator;
use App\Administration\Domain\ValueObject\AdministratorId;
use App\Administration\Domain\ValueObject\RoleId;
use App\Administration\Domain\ValueObject\SignInAccountId;

/**
 * In-memory adapter for the {@see Administrators} port. Used by
 * domain/application unit tests so they stay #[Small] and never boot
 * Doctrine.
 */
final class InMemoryAdministrators implements Administrators
{
    /** @var array<string, Administrator> indexed by AdministratorId->value */
    private array $byId = [];

    public function add(Administrator $administrator): void
    {
        // Checked before the sign-in-account guard, matching
        // DoctrineAdministrators::add(), which hits the primary-key
        // constraint before it can even reach the sign-in-account
        // unique index: an id collision must never silently overwrite
        // the existing aggregate's tenure history.
        if (isset($this->byId[$administrator->id()->value])) {
            throw AdministratorAlreadyExists::withId($administrator->id());
        }

        if ($this->existsForSignInAccount($administrator->signInAccountId())) {
            throw SignInAccountAlreadyAnAdministrator::for($administrator->signInAccountId());
        }

        $this->byId[$administrator->id()->value] = $administrator;
    }

    public function save(Administrator $administrator): void
    {
        // Mirror DoctrineAdministrators semantics: save() only persists
        // changes to an already-added aggregate; calling it on an
        // unknown administrator is a programming error, not a
        // "create if missing" upsert.
        if (!isset($this->byId[$administrator->id()->value])) {
            throw AdministratorNotFound::withId($administrator->id());
        }

        foreach ($this->byId as $existingId => $existing) {
            if (
                $existingId !== $administrator->id()->value
                && $existing->signInAccountId()->equals($administrator->signInAccountId())
            ) {
                throw SignInAccountAlreadyAnAdministrator::for($administrator->signInAccountId());
            }
        }

        $this->byId[$administrator->id()->value] = $administrator;
    }

    public function byId(AdministratorId $id): Administrator
    {
        return $this->byId[$id->value] ?? throw AdministratorNotFound::withId($id);
    }

    public function forSignInAccount(SignInAccountId $id): Administrator
    {
        foreach ($this->byId as $administrator) {
            if ($administrator->signInAccountId()->equals($id)) {
                return $administrator;
            }
        }

        throw AdministratorNotFound::forSignInAccount($id);
    }

    public function existsForSignInAccount(SignInAccountId $id): bool
    {
        foreach ($this->byId as $administrator) {
            if ($administrator->signInAccountId()->equals($id)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<Administrator>
     */
    public function activeHoldersOfRole(RoleId $roleId): array
    {
        return array_values(array_filter(
            $this->byId,
            static fn (Administrator $administrator): bool => $administrator->isActive()
                && $administrator->assignedRoles()->contains($roleId),
        ));
    }
}
