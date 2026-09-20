<?php

declare(strict_types=1);

namespace App\Administration\Application\Command;

/**
 * Primitive-only command DTO for the DefineRank use case.
 *
 * $actorKind and $actorId carry the acting {@see \App\Administration\Domain\ValueObject\Actor}
 * as a primitive pair — see {@see \App\Administration\Application\ActorAssembler}
 * for how the handler reassembles them. Every write command in this
 * context carries the same trailing pair.
 *
 * Roles are not assigned at definition time: a new rank starts with no
 * granted roles, added afterwards via GrantRoleToRank.
 */
final readonly class DefineRank
{
    public function __construct(
        public string $name,
        public int $seniority,
        public string $actorKind,
        public ?string $actorId = null,
    ) {
    }
}
