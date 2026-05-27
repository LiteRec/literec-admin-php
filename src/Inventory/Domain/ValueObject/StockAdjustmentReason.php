<?php

declare(strict_types=1);

namespace App\Inventory\Domain\ValueObject;

/**
 * Sub-category for variance rows posted from the LRA-87 Take Inventory
 * bulk page. Sits underneath {@see StockMovementReason::ADJUSTMENT} so
 * the legacy operator categories (Damaged / Found / Theft / Miscount /
 * Other) survive the rewrite without polluting the high-level
 * {@see StockMovementReason} enum used by every consume path.
 *
 * Persisted in the inventory_stock_movements ledger row by the LRA-94
 * subscriber, prefixed onto the operator_note column as `[CATEGORY]
 * free-text` so the LRA-88 movement-history filter can match on either
 * the category or the free text without an extra column.
 */
enum StockAdjustmentReason: string
{
    case DAMAGED = 'damaged';
    case FOUND = 'found';
    case THEFT = 'theft';
    case MISCOUNT = 'miscount';
    case OTHER = 'other';
}
