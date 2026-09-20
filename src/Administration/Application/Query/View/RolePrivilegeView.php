<?php

declare(strict_types=1);

namespace App\Administration\Application\Query\View;

final readonly class RolePrivilegeView
{
    public function __construct(
        public string $name,
        public string $displayName,
    ) {
    }
}
