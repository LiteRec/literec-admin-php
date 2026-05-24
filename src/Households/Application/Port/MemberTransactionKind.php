<?php

declare(strict_types=1);

namespace App\Households\Application\Port;

/**
 * Closed set of transaction kinds the Member Transaction History card
 * (LRA-45) can render. Owned by the read-side port rather than the
 * Transactions bounded context because that context does not exist yet —
 * the Anti-Corruption Layer for Transactions, when it lands, will be
 * responsible for translating its own internal kind enum into this one.
 */
enum MemberTransactionKind: string
{
    case Sale = 'SALE';
    case Refund = 'REFUND';
    case Adjustment = 'ADJUSTMENT';
    case Membership = 'MEMBERSHIP';
}
