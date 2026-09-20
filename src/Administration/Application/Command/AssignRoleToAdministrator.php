<?php

declare(strict_types=1);

namespace App\Administration\Application\Command;

/**
 * Primitive-only command DTO for the AssignRoleToAdministrator use case.
 *
 * See {@see \App\Administration\Application\Command\DefineRank} for the
 * trailing actor-pair convention shared by every write command in this
 * context.
 */
final readonly class AssignRoleToAdministrator
{
    public function __construct(
        public string $administratorId,
        public string $roleId,
        public string $actorKind,
        public ?string $actorId = null,
    ) {
    }
}
