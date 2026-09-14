<?php

declare(strict_types=1);

namespace App\Ui\CashRegister;

/**
 * One row in the Builder pane's program search results. Presentation-only
 * sample data; `price` arrives pre-formatted for display while `priceCents`
 * backs the client-side "Add to sale" total the add-on toggles recompute.
 */
final readonly class ProgramResult
{
    public function __construct(
        public string $code,
        public string $name,
        public string $schedule,
        public string $spots,
        public string $price,
        public int $priceCents,
        public bool $selected = false,
    ) {
    }
}
