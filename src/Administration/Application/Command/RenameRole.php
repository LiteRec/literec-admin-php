<?php

declare(strict_types=1);

namespace App\Administration\Application\Command;

/**
 * Primitive-only command DTO for the RenameRole use case.
 *
 * See {@see DefineRole} for the trailing actor-pair convention shared by
 * every write command in this context.
 */
final readonly class RenameRole
{
    public function __construct(
        public string $roleId,
        public string $name,
        public string $actorKind,
        public ?string $actorId = null,
    ) {
    }
}
