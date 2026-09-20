<?php

declare(strict_types=1);

namespace App\Administration\Domain;

use App\Administration\Domain\ValueObject\AdministratorId;
use App\Administration\Domain\ValueObject\PrivilegeGrants;

/**
 * The single port the rest of the application resolves an administrator's
 * effective privileges through (LRA-270). This is the one source of
 * truth {@see \App\Administration\Infrastructure\Security\PrivilegeVoter}
 * (the allow/deny decision) and {@see \App\Administration\Application\Query\GetGrantedPrivilegesHandler}
 * (the read path the UI uses to decide what to render) both resolve
 * through — two decisions, never two code paths.
 *
 * The production binding is {@see \App\Administration\Infrastructure\Security\RequestScopedEffectivePrivileges},
 * a per-request-memoising decorator over {@see \App\Administration\Infrastructure\Security\UnionOfGrantSources}.
 * No implementation may cache beyond the current request: a change to a
 * role or a revocation must take effect starting with the very next
 * request.
 */
interface EffectivePrivileges
{
    public function forAdministrator(AdministratorId $id): PrivilegeGrants;
}
