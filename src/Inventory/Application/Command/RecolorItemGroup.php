<?php

declare(strict_types=1);

namespace App\Inventory\Application\Command;

/**
 * Primitive-only command DTO for recoloring an existing item group
 * (LRA-96; the recolor aggregate method already exists, only the bus
 * wiring was deferred).
 */
final readonly class RecolorItemGroup
{
    public function __construct(
        public string $groupId,
        public string $colorHex,
    ) {
    }
}
