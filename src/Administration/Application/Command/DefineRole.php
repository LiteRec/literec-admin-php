<?php

declare(strict_types=1);

namespace App\Administration\Application\Command;

/**
 * Primitive-only command DTO for the DefineRole use case.
 *
 * $actorKind and $actorId carry the acting {@see \App\Administration\Domain\ValueObject\Actor}
 * as a primitive pair — see {@see \App\Administration\Application\ActorAssembler}
 * for how the handler reassembles them. Every write command in this
 * context carries the same trailing pair.
 */
final readonly class DefineRole
{
    /**
     * @param list<string> $privilegeNames Privilege enum string values,
     *        e.g. ['VIEW_USERS'].
     */
    public function __construct(
        public string $name,
        public string $description,
        public array $privilegeNames,
        public string $actorKind,
        public ?string $actorId = null,
    ) {
    }
}
