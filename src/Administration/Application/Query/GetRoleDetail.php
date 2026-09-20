<?php

declare(strict_types=1);

namespace App\Administration\Application\Query;

/**
 * Primitive-only query DTO for the Role detail view.
 */
final readonly class GetRoleDetail
{
    public function __construct(
        public string $roleId,
    ) {
    }
}
