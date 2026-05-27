<?php

declare(strict_types=1);

namespace App\Inventory\Domain\ValueObject;

/**
 * Domain enum mirroring the string `kind` column on the
 * inventory_stock_movements ledger (LRA-94). The underlying string
 * values must stay byte-identical to the literals emitted by
 * {@see App\Inventory\Infrastructure\Persistence\Doctrine\DoctrineStockMovementLedger}
 * — the ledger writer keeps emitting raw strings (its public method
 * names already encode the kind), and this enum is the type-safe lens
 * the LRA-88 history page uses to validate filter inputs and render
 * human labels.
 *
 * Each case's `label()` is the operator-facing display string used by
 * the kind filter dropdown and the movements table.
 */
enum StockMovementKind: string
{
    case RECEIVED = 'RECEIVED';
    case CONSUMED = 'CONSUMED';
    case RETURNED = 'RETURNED';
    case TRANSFERRED_OUT = 'TRANSFERRED_OUT';
    case TRANSFERRED_IN = 'TRANSFERRED_IN';
    case ADJUSTED_INCREASE = 'ADJUSTED_INCREASE';
    case ADJUSTED_DECREASE = 'ADJUSTED_DECREASE';

    public function label(): string
    {
        return match ($this) {
            self::RECEIVED => 'Received',
            self::CONSUMED => 'Consumed',
            self::RETURNED => 'Returned',
            self::TRANSFERRED_OUT => 'Transferred out',
            self::TRANSFERRED_IN => 'Transferred in',
            self::ADJUSTED_INCREASE => 'Adjusted +',
            self::ADJUSTED_DECREASE => 'Adjusted −',
        };
    }

    /**
     * True when the kind represents stock leaving on-hand (negative
     * direction for display purposes). Used by the movements table
     * to render quantity with a leading minus sign.
     */
    public function isOutbound(): bool
    {
        return match ($this) {
            self::CONSUMED, self::TRANSFERRED_OUT, self::ADJUSTED_DECREASE => true,
            self::RECEIVED, self::RETURNED, self::TRANSFERRED_IN, self::ADJUSTED_INCREASE => false,
        };
    }
}
