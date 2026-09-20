<?php

declare(strict_types=1);

namespace App\Administration\Domain;

use App\Administration\Domain\ValueObject\AdministratorId;
use App\Administration\Domain\ValueObject\PrivilegeGrants;

/**
 * The extension point for granting privileges to an administrator
 * (LRA-270). An administrator's effective privileges are the union of
 * whatever every registered source contributes — adding a new way to
 * hold a privilege means registering one more implementation of this
 * interface via the `administration.privilege_grant_source` DI tag
 * (see {@see \App\Administration\Infrastructure\Security\UnionOfGrantSources}),
 * never editing the resolver, the voter, or any call site. That is what
 * lets LRA-271 add time-boxed vendor support access without opening a
 * second path through authorization.
 *
 * Implementations never need to check whether $id is currently an
 * active administrator: {@see UnionOfGrantSources} consults standing
 * once, before any source runs, so a revoked administrator resolves to
 * an empty set without every source having to remember to check.
 */
interface PrivilegeGrantSource
{
    public function grantsFor(AdministratorId $id): PrivilegeGrants;
}
