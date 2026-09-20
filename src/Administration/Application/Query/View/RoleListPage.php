<?php

declare(strict_types=1);

namespace App\Administration\Application\Query\View;

final readonly class RoleListPage
{
    /**
     * @param list<RoleSummaryView> $roles
     */
    public function __construct(
        public array $roles,
    ) {
    }
}
