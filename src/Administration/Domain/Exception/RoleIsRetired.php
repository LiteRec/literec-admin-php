<?php

declare(strict_types=1);

namespace App\Administration\Domain\Exception;

use App\Administration\Domain\ValueObject\RoleId;
use DomainException;

/**
 * Raised when a use case reads a role's retired state and finds it
 * already decommissioned — distinct from {@see RoleAlreadyRetired},
 * which guards Role::retire() itself against a double retirement. This
 * one guards a *different* aggregate's use of the role, e.g.
 * {@see \App\Administration\Application\Command\GrantRoleToRankHandler}
 * refusing to grant a retired role to a rank.
 */
final class RoleIsRetired extends DomainException implements AdministrationDomainException
{
    public static function for(RoleId $id): self
    {
        return new self(sprintf('Role %s is retired.', $id->value));
    }
}
