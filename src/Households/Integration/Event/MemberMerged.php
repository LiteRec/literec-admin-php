<?php

declare(strict_types=1);

namespace App\Households\Integration\Event;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Published-language integration event raised after Households merges a
 * duplicate member into a survivor (LRA-208). Downstream contexts (none
 * exist yet — future Transactions, Memberships, Activities, Rentals)
 * subscribe to repoint their own member_id-carrying rows from the merged
 * member to the survivor.
 *
 * Contract:
 *   - Fields use wire-safe scalars plus \DateTimeImmutable so the
 *     envelope can be serialised onto any Messenger transport without
 *     leaking Households domain types.
 *   - Field set is additive-only. Never repurpose, rename, or remove a
 *     field; introduce a new event class if a breaking change is needed.
 *   - Ordering guarantee: dispatched only after the writing transaction
 *     commits (via DispatchAfterCurrentBusStamp combined with the
 *     command.bus doctrine_transaction middleware). At-least-once
 *     delivery via the async transport; every consumer must be
 *     idempotent, keyed on $mergedMemberId.
 *   - Households owns the merged state and is the sole publisher of this
 *     event; it never repoints another context's rows itself — each
 *     consumer repoints its own member_id columns from $mergedMemberId to
 *     $survivorMemberId on arrival.
 */
final readonly class MemberMerged
{
    public function __construct(
        public string $survivorHouseholdId,
        public string $survivorMemberId,
        public string $mergedHouseholdId,
        public string $mergedMemberId,
        public DateTimeImmutable $occurredAt,
    ) {
        if ($survivorHouseholdId === '') {
            throw new InvalidArgumentException('MemberMerged.survivorHouseholdId must not be empty.');
        }
        if ($survivorMemberId === '') {
            throw new InvalidArgumentException('MemberMerged.survivorMemberId must not be empty.');
        }
        if ($mergedHouseholdId === '') {
            throw new InvalidArgumentException('MemberMerged.mergedHouseholdId must not be empty.');
        }
        if ($mergedMemberId === '') {
            throw new InvalidArgumentException('MemberMerged.mergedMemberId must not be empty.');
        }
    }
}
