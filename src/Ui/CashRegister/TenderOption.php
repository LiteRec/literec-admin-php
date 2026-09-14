<?php

declare(strict_types=1);

namespace App\Ui\CashRegister;

/**
 * One tender tile in the Quick sale receipt rail footer. Presentation-only
 * sample data; selecting a tender is Alpine state only — no payment is taken.
 */
final readonly class TenderOption
{
    public function __construct(
        public string $id,
        public string $label,
        public string $icon,
    ) {
    }
}
