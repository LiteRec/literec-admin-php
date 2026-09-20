<?php

declare(strict_types=1);

namespace App\Tests\Support\Fake;

use App\Administration\Domain\Administrator;
use App\Administration\Domain\Administrators;
use App\Administration\Domain\ValueObject\AdministratorId;
use App\Administration\Domain\ValueObject\RoleId;
use App\Administration\Domain\ValueObject\SignInAccountId;

/**
 * Decorates an {@see Administrators} adapter, counting add()/save() calls
 * so a handler test can prove persistence was actually requested. Same
 * rationale as {@see SaveCountingRanks}: the in-memory adapter
 * ({@see \App\Administration\Infrastructure\Persistence\InMemory\InMemoryAdministrators})
 * stores the same instance a handler mutates in place, so an assertion
 * against the stored aggregate alone stays green even if the handler
 * under test never calls save() at all.
 */
final class SaveCountingAdministrators implements Administrators
{
    public int $addCalls = 0;
    public int $saveCalls = 0;

    public function __construct(private readonly Administrators $inner)
    {
    }

    public function add(Administrator $administrator): void
    {
        ++$this->addCalls;
        $this->inner->add($administrator);
    }

    public function save(Administrator $administrator): void
    {
        ++$this->saveCalls;
        $this->inner->save($administrator);
    }

    public function byId(AdministratorId $id): Administrator
    {
        return $this->inner->byId($id);
    }

    public function forSignInAccount(SignInAccountId $id): Administrator
    {
        return $this->inner->forSignInAccount($id);
    }

    public function existsForSignInAccount(SignInAccountId $id): bool
    {
        return $this->inner->existsForSignInAccount($id);
    }

    public function activeHoldersOfRole(RoleId $roleId): array
    {
        return $this->inner->activeHoldersOfRole($roleId);
    }
}
