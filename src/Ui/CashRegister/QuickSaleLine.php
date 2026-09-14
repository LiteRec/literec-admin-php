<?php

declare(strict_types=1);

namespace App\Ui\CashRegister;

/**
 * One seeded line in the Quick sale receipt rail on first render. `tileId`
 * matches a `QuickSaleTile::$id` so the Alpine-driven sale state can key its
 * quantities the same way once the page hydrates. Presentation-only sample
 * data — no persistence backs this seed.
 */
final readonly class QuickSaleLine
{
    public function __construct(
        public string $tileId,
        public int $quantity,
    ) {
    }
}
