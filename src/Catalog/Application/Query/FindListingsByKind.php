<?php

declare(strict_types=1);

namespace App\Catalog\Application\Query;

final readonly class FindListingsByKind
{
    public function __construct(
        public string $kind,
        public int $offset = 0,
        public int $limit = 50,
    ) {
    }
}
