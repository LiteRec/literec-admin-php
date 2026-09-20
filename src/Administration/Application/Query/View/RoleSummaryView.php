<?php

declare(strict_types=1);

namespace App\Administration\Application\Query\View;

final readonly class RoleSummaryView
{
    public function __construct(
        public string $id,
        public string $name,
        public int $privilegeCount,
        public bool $retired,
    ) {
    }
}
