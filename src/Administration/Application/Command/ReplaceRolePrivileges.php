<?php

declare(strict_types=1);

namespace App\Administration\Application\Command;

/**
 * Primitive-only command DTO for the ReplaceRolePrivileges use case — the
 * whole-bundle save the edit screen posts, as distinct from the
 * single-privilege GrantPrivilegeToRole/RevokePrivilegeFromRole
 * operations.
 *
 * See {@see DefineRole} for the trailing actor-pair convention shared by
 * every write command in this context.
 */
final readonly class ReplaceRolePrivileges
{
    /**
     * @param list<string> $privilegeNames Privilege enum string values,
     *        e.g. ['VIEW_USERS'].
     */
    public function __construct(
        public string $roleId,
        public array $privilegeNames,
        public string $actorKind,
        public ?string $actorId = null,
    ) {
    }
}
