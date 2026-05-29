<?php

declare(strict_types=1);

namespace App\Ui\CashRegister;

/**
 * One line in the Quick sale rail (the current sale). Presentation-only sample
 * data; price arrives pre-formatted.
 */
final readonly class QuickSaleLine
{
    public function __construct(
        public string $name,
        public int $quantity,
        public string $price,
    ) {
    }
}
