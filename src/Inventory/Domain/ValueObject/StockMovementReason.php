<?php

declare(strict_types=1);

namespace App\Inventory\Domain\ValueObject;

/**
 * Why a stock movement occurred.
 *
 * SALE / RENTAL_CHECKOUT cover the consume paths exercised in LRA-74.
 * ADJUSTMENT, TRANSFER_OUT, TRANSFER_IN are wired up by the application
 * services in LRA-75 and LRA-78; declared here so the StockMovementRecorded
 * event signature is stable across the sprint.
 */
enum StockMovementReason: string
{
    case SALE = 'sale';
    case RENTAL_CHECKOUT = 'rental_checkout';
    case RETURN = 'return';
    case ADJUSTMENT = 'adjustment';
    case TRANSFER_OUT = 'transfer_out';
    case TRANSFER_IN = 'transfer_in';
}
