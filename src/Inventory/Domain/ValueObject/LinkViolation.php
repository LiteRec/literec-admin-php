<?php

declare(strict_types=1);

namespace App\Inventory\Domain\ValueObject;

/**
 * The named reason a {@see App\Inventory\Domain\ItemLink} blocks a
 * master sale.
 *
 * Returned by {@see App\Inventory\Domain\ItemLink::wouldViolateAt()};
 * `null` from that method means the link does not block this sale.
 */
enum LinkViolation: string
{
    case RESERVED_QUANTITY_BREACHED = 'reserved_quantity_breached';
    case MIN_REQUIRED_NOT_MET = 'min_required_not_met';
    case MAX_PER_PURCHASE_EXCEEDED = 'max_per_purchase_exceeded';
}
