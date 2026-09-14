<?php

declare(strict_types=1);

namespace App\Ui\CashRegister;

/**
 * Everything the Quick sale screen renders: the category filter chips, the
 * touch-tile item picker, the tender options, and the seeded receipt rail
 * with its (server-rendered fallback) totals. `taxRateBasisPoints` backs the
 * Alpine-driven client-side recalculation once a tile is tapped or a
 * stepper changes the sale; it is an integer (e.g. 700 = 7.00%) rather than a
 * float so the client's cents-based arithmetic never drifts from what a
 * future PHP-side recalculation of the same rate would produce.
 * `subtotal`/`tax`/`total` are the matching pre-formatted totals for the
 * initial, pre-hydration render. Presentation-only sample data — no
 * participant lookup and no persistence.
 */
final readonly class QuickSaleData
{
    /**
     * @param list<string> $categories
     * @param list<QuickSaleTile> $tiles
     * @param list<QuickSaleLine> $sale
     * @param list<TenderOption> $tenders
     */
    public function __construct(
        public array $categories,
        public array $tiles,
        public array $sale,
        public array $tenders,
        public int $taxRateBasisPoints,
        public string $subtotal,
        public string $taxLabel,
        public string $tax,
        public string $total,
    ) {
    }
}
