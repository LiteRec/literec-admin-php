<?php

declare(strict_types=1);

namespace App\Catalog\Application\Query;

use InvalidArgumentException;

final readonly class FindListingsByKind
{
    public function __construct(
        public string $kind,
        public int $offset = 0,
        public int $limit = 50,
    ) {
        if ($offset < 0) {
            throw new InvalidArgumentException(
                sprintf('Pagination offset must be non-negative; got %d.', $offset)
            );
        }

        if ($limit < 1) {
            throw new InvalidArgumentException(
                sprintf('Pagination limit must be at least 1; got %d.', $limit)
            );
        }
    }
}
