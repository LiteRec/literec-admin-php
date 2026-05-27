<?php

declare(strict_types=1);

namespace App\Inventory\Domain\ValueObject;

/**
 * Sign discriminator for ADJUSTED ledger rows.
 *
 * {@see StockMovementLedger::recordAdjusted()} accepts a non-negative
 * {@see Quantity} so the absolute count is preserved verbatim; this
 * enum carries whether the operator booked a positive variance
 * (INCREASE, e.g. "found 5 in back room") or a negative variance
 * (DECREASE, e.g. "shrinkage of 4"). Without the discriminator the
 * two paths collapse into indistinguishable rows on the ledger, which
 * breaks the LRA-91 Entry Log report and the LRA-88 movement history
 * filter.
 */
enum StockAdjustmentDirection: string
{
    case INCREASE = 'increase';
    case DECREASE = 'decrease';
}
