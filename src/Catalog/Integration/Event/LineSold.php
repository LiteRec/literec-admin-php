<?php

declare(strict_types=1);

namespace App\Catalog\Integration\Event;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Published-language integration event raised after a Catalog Listing is
 * sold in the Transactions context. Downstream contexts (Inventory now;
 * Programs / Memberships / Rentals later) subscribe to react to the sale
 * — for example, deducting on-hand stock or activating a membership.
 *
 * Contract:
 *   - Fields are primitive-only so the envelope can be serialised onto
 *     any Messenger transport without leaking domain types.
 *   - Field set is additive-only. Never repurpose, rename, or remove a
 *     field; introduce a new event class if a breaking change is needed.
 *   - Ordering guarantee: dispatched only after the writing transaction
 *     commits (via DispatchAfterCurrentBusStamp combined with the
 *     command.bus doctrine_transaction middleware). At-least-once
 *     delivery via the async transport; consumers must be idempotent
 *     keyed on (transactionId, listingId).
 *
 * Catalog defines the contract; the future Transactions context emits
 * it. Catalog itself never publishes this event.
 */
final readonly class LineSold
{
    public function __construct(
        public string $listingId,
        public string $listingKind,
        public string $listingCode,
        public int $quantity,
        public string $facilityCode,
        public string $transactionId,
        public DateTimeImmutable $occurredAt,
    ) {
        if ($listingId === '') {
            throw new InvalidArgumentException('LineSold.listingId must not be empty.');
        }
        if ($listingKind === '') {
            throw new InvalidArgumentException('LineSold.listingKind must not be empty.');
        }
        if ($listingCode === '') {
            throw new InvalidArgumentException('LineSold.listingCode must not be empty.');
        }
        if ($facilityCode === '') {
            throw new InvalidArgumentException('LineSold.facilityCode must not be empty.');
        }
        if ($transactionId === '') {
            throw new InvalidArgumentException('LineSold.transactionId must not be empty.');
        }
        if ($quantity < 1) {
            throw new InvalidArgumentException(
                sprintf('LineSold.quantity must be at least 1; got %d.', $quantity)
            );
        }
    }
}
