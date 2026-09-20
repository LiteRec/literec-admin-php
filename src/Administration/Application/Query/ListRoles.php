<?php

declare(strict_types=1);

namespace App\Administration\Application\Query;

/**
 * Primitive-only query DTO for the Roles list page.
 */
final readonly class ListRoles
{
    public function __construct(
        public bool $includeRetired = false,
    ) {
    }
}
