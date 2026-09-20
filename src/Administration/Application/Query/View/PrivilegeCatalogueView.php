<?php

declare(strict_types=1);

namespace App\Administration\Application\Query\View;

final readonly class PrivilegeCatalogueView
{
    /**
     * @param list<PrivilegeGroupView> $groups
     */
    public function __construct(
        public array $groups,
    ) {
    }
}
