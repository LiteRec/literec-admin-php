<?php

declare(strict_types=1);

namespace App\Ui\CashRegister;

/**
 * One toggle pill in the Builder pane's add-on options. Presentation-only
 * sample data; `price` arrives pre-formatted for display while `priceCents`
 * backs the client-side "Add to sale" total; `checked` reflects whether the
 * option is pre-selected in the mockup.
 */
final readonly class AddOnOption
{
    public function __construct(
        public string $name,
        public string $price,
        public int $priceCents,
        public bool $checked = false,
    ) {
    }
}
