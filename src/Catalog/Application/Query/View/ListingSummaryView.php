<?php

declare(strict_types=1);

namespace App\Catalog\Application\Query\View;

final readonly class ListingSummaryView
{
    public function __construct(
        public string $id,
        public string $code,
        public string $kind,
        public string $name,
        public bool $archived,
    ) {
    }
}
