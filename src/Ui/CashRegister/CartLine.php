<?php

declare(strict_types=1);

namespace App\Ui\CashRegister;

/**
 * One line in the shopping cart. `kind` is one of program | membership | item |
 * rental; the template maps it to the matching lr-badge variant. Presentation-
 * only sample data; price arrives pre-formatted.
 */
final readonly class CartLine
{
    public function __construct(
        public string $kind,
        public string $name,
        public string $code,
        public int $quantity,
        public string $price,
    ) {
    }
}
