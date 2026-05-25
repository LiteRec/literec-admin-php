<?php

declare(strict_types=1);

namespace App\Catalog\Application\Query\View;

final readonly class FeeView
{
    public function __construct(
        public int $amountCents,
        public string $currency,
        public string $label,
    ) {
    }
}
