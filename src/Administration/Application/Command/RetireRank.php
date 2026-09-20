<?php

declare(strict_types=1);

namespace App\Administration\Application\Command;

/**
 * Primitive-only command DTO for the RetireRank use case.
 *
 * See {@see DefineRank} for the trailing actor-pair convention shared by
 * every write command in this context.
 */
final readonly class RetireRank
{
    public function __construct(
        public string $rankId,
        public string $actorKind,
        public ?string $actorId = null,
    ) {
    }
}
