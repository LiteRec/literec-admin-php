<?php

declare(strict_types=1);

namespace App\Households\Integration\Event;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Published-language integration event raised after Households splits
 * transaction attribution off a source member onto a newly created
 * member (LRA-209). The (not-yet-existing) Transactions context will
 * subscribe to this to reassign payer/participant attribution for
 * $transactionIds from $sourceMemberId to $newMemberId.
 *
 * Contract: see {@see MemberMerged} — same wire-safe-scalars, additive-only
 * field set, post-commit-only dispatch, and at-least-once/idempotent-consumer
 * rules apply here.
 */
final readonly class MemberTransactionsSplitOff
{
    /**
     * @param list<string> $transactionIds
     */
    public function __construct(
        public string $householdId,
        public string $sourceMemberId,
        public string $newMemberId,
        public array $transactionIds,
        public DateTimeImmutable $occurredAt,
    ) {
        if ($householdId === '') {
            throw new InvalidArgumentException('MemberTransactionsSplitOff.householdId must not be empty.');
        }
        if ($sourceMemberId === '') {
            throw new InvalidArgumentException('MemberTransactionsSplitOff.sourceMemberId must not be empty.');
        }
        if ($newMemberId === '') {
            throw new InvalidArgumentException('MemberTransactionsSplitOff.newMemberId must not be empty.');
        }
        if ($transactionIds === []) {
            throw new InvalidArgumentException('MemberTransactionsSplitOff.transactionIds must not be empty.');
        }
    }
}
