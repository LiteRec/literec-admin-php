<?php

declare(strict_types=1);

namespace App\Catalog\Application\Query;

use App\Catalog\Application\Exception\InvalidListingsPagination;

final readonly class FindListingsByKind
{
    public function __construct(
        public string $kind,
        public int $offset = 0,
        public int $limit = 50,
    ) {
        if ($offset < 0) {
            throw InvalidListingsPagination::negativeOffset($offset);
        }

        if ($limit < 1) {
            throw InvalidListingsPagination::limitBelowOne($limit);
        }
    }
}
