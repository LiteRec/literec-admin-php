<?php

declare(strict_types=1);

namespace App\Inventory\Domain;

/**
 * Lifecycle status of a {@see PurchaseOrder}.
 *
 * Draft → Sent → PartiallyReceived → FullyReceived → Verified is the
 * happy path; Cancelled is a terminal state from Draft or Sent. The
 * aggregate enforces the transitions; this enum is the persisted form.
 */
enum PurchaseOrderStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case PartiallyReceived = 'partially_received';
    case FullyReceived = 'fully_received';
    case Verified = 'verified';
    case Cancelled = 'cancelled';
}
