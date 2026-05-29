<?php

declare(strict_types=1);

namespace App\Ui\CashRegister;

/**
 * One row in the Builder pane's add-on options table. Presentation-only sample
 * data; `checked` reflects whether the option is pre-selected in the mockup.
 */
final readonly class AddOnOption
{
    public function __construct(
        public string $name,
        public string $price,
        public bool $checked = false,
    ) {
    }
}
