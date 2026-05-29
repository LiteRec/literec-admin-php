<?php

declare(strict_types=1);

namespace App\Ui\CashRegister;

/**
 * The Summary pane's totals block. Presentation-only sample data; every amount
 * arrives pre-formatted.
 */
final readonly class SaleTotals
{
    public function __construct(
        public string $subtotal,
        public string $discounts,
        public string $tax,
        public string $total,
    ) {
    }
}
