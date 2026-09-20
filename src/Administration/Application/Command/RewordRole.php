<?php

declare(strict_types=1);

namespace App\Administration\Application\Command;

/**
 * Primitive-only command DTO for the RewordRole use case.
 *
 * See {@see DefineRole} for the trailing actor-pair convention shared by
 * every write command in this context.
 */
final readonly class RewordRole
{
    public function __construct(
        public string $roleId,
        public string $description,
        public string $actorKind,
        public ?string $actorId = null,
    ) {
    }
}
