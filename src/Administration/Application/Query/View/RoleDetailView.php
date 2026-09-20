<?php

declare(strict_types=1);

namespace App\Administration\Application\Query\View;

final readonly class RoleDetailView
{
    /**
     * @param list<RolePrivilegeView> $privileges
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $description,
        public array $privileges,
        public bool $retired,
    ) {
    }
}
