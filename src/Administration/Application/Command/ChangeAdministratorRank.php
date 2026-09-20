<?php

declare(strict_types=1);

namespace App\Administration\Application\Command;

/**
 * Primitive-only command DTO for the ChangeAdministratorRank use case.
 *
 * See {@see \App\Administration\Application\Command\DefineRank} for the
 * trailing actor-pair convention shared by every write command in this
 * context.
 */
final readonly class ChangeAdministratorRank
{
    public function __construct(
        public string $administratorId,
        public string $rankId,
        public string $actorKind,
        public ?string $actorId = null,
    ) {
    }
}
