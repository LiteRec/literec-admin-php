<?php

declare(strict_types=1);

namespace App\Catalog\Application\Command;

final readonly class RenameListing
{
    public function __construct(
        public string $listingId,
        public string $name,
    ) {
    }
}
