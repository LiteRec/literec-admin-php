<?php

declare(strict_types=1);

namespace App\Inventory\Application\Query\View;

use DateTimeImmutable;

/**
 * Row projection for the LRA-90 Purchase Orders list table.
 *
 * Carries pre-computed aggregate totals (ordered/received units, total
 * cost in cents) so the template never has to re-derive them from raw
 * line data.
 *
 * `createdAt` / `sentAt` are exposed as `DateTimeImmutable` here
 * (deliberately inconsistent with {@see PurchaseOrderDetailView}'s
 * ISO-string fields) so the list template can call Twig's `|date('Y-m-d')`
 * filter for compact column formatting. The detail page renders full
 * ISO timestamps verbatim and therefore needs no DateTime instance.
 */
final readonly class PurchaseOrderSummaryView
{
    public function __construct(
        public string $purchaseOrderId,
        public string $vendorId,
        public string $facilityCode,
        public string $status,
        public int $lineCount,
        public int $totalOrderedUnits,
        public int $totalReceivedUnits,
        public int $totalCostCents,
        public DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $sentAt,
    ) {
    }
}
