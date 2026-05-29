<?php

declare(strict_types=1);

namespace App\Ui\CashRegister;

/**
 * The program highlighted in the Builder pane's summary block. Presentation-only
 * sample data; price arrives pre-formatted.
 */
final readonly class ProgramSelection
{
    public function __construct(
        public string $code,
        public string $name,
        public string $schedule,
        public string $price,
    ) {
    }
}
