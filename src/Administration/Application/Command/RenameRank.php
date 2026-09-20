<?php

declare(strict_types=1);

namespace App\Administration\Application\Command;

/**
 * Primitive-only command DTO for the RenameRank use case.
 *
 * See {@see DefineRank} for the trailing actor-pair convention shared by
 * every write command in this context.
 */
final readonly class RenameRank
{
    public function __construct(
        public string $rankId,
        public string $name,
        public string $actorKind,
        public ?string $actorId = null,
    ) {
    }
}
