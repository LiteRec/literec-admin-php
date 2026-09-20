<?php

declare(strict_types=1);

namespace App\Administration\Application\Query\View;

final readonly class PrivilegeGroupView
{
    /**
     * @param list<PrivilegeView> $privileges
     */
    public function __construct(
        public string $name,
        public string $displayName,
        public array $privileges,
    ) {
    }
}
