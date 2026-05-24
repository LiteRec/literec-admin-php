<?php

declare(strict_types=1);

namespace App\Households\Application\Port;

/**
 * Read-side DTO for a single row of the Member Transaction History card
 * (LRA-45). Lives in the Application layer's port namespace because the
 * UI consumes it through the {@see MemberTransactionHistory} port; the
 * real Transactions bounded context (and any adapter that fronts it) is
 * responsible for projecting its own state into this shape.
 *
 * `amount` is intentionally a pre-formatted display string ("$45.00")
 * rather than a Money value object. The Money VO is out of scope for
 * this card — the read model is purely for display, and keeping the
 * field a string means the stub adapter does not have to import (or
 * reinvent) a domain primitive that belongs to a context that does not
 * exist yet. When the real Transactions context lands, its ACL adapter
 * will format Money into this string at the boundary.
 *
 * `status` and `paymentMethod` are likewise pre-formatted display
 * strings — the UI just renders them in the table.
 */
final readonly class MemberTransactionRow
{
    public function __construct(
        public string $transactionId,
        public \DateTimeImmutable $occurredAt,
        public MemberTransactionKind $kind,
        public string $description,
        public string $amount,
        public string $status,
        public string $paymentMethod,
    ) {
    }
}
