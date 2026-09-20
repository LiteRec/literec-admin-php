<?php

declare(strict_types=1);

namespace App\Administration\Application\Query\View;

final readonly class PrivilegeView
{
    public function __construct(
        public string $name,
        public string $displayName,
        public string $description,
        public int $order,
    ) {
    }
}
