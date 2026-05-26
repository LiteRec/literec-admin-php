<?php

declare(strict_types=1);

namespace App\Inventory\Application\Command;

/**
 * Primitive-only command DTO for the CreateItemGroup use case.
 *
 * Empty $facilityCodes signals an "all facilities" scope; a non-empty
 * list scopes the group to the listed facilities only (dedup-sorted
 * inside the FacilityScope VO).
 */
final readonly class CreateItemGroup
{
    /**
     * @param list<string> $facilityCodes
     */
    public function __construct(
        public string $name,
        public string $colorHex,
        public array $facilityCodes,
    ) {
    }
}
