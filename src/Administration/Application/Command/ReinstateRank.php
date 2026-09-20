<?php

declare(strict_types=1);

namespace App\Administration\Application\Command;

/**
 * Primitive-only command DTO for the ReinstateRank use case — the inverse
 * of RetireRank, clearing a rank's retired flag.
 *
 * See {@see DefineRank} for the trailing actor-pair convention shared by
 * every write command in this context.
 */
final readonly class ReinstateRank
{
    public function __construct(
        public string $rankId,
        public string $actorKind,
        public ?string $actorId = null,
    ) {
    }
}
