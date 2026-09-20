<?php

declare(strict_types=1);

namespace App\Administration\Application\Command;

/**
 * Primitive-only command DTO for the GrantPrivilegeToRole use case — the
 * single-privilege grant operation, as distinct from the whole-bundle
 * ReplaceRolePrivileges save.
 *
 * See {@see DefineRole} for the trailing actor-pair convention shared by
 * every write command in this context.
 */
final readonly class GrantPrivilegeToRole
{
    public function __construct(
        public string $roleId,
        public string $privilegeName,
        public string $actorKind,
        public ?string $actorId = null,
    ) {
    }
}
