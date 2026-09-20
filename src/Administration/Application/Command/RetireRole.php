<?php

declare(strict_types=1);

namespace App\Administration\Application\Command;

/**
 * Primitive-only command DTO for the RetireRole use case.
 *
 * The guard that refuses to retire a still-referenced role belongs to
 * slice (b) of LRA-268 — the ports it consults (rank and administrator
 * assignment) do not exist until LRA-267 and LRA-269 land.
 *
 * See {@see DefineRole} for the trailing actor-pair convention shared by
 * every write command in this context.
 */
final readonly class RetireRole
{
    public function __construct(
        public string $roleId,
        public string $actorKind,
        public ?string $actorId = null,
    ) {
    }
}
