<?php

declare(strict_types=1);

namespace App\Households\Infrastructure\Acl\Stub;

use App\Households\Application\Port\MemberTransactionHistory;
use App\Households\Application\Port\MemberTransactionKind;
use App\Households\Application\Port\MemberTransactionPage;
use App\Households\Application\Port\MemberTransactionRow;
use App\Households\Domain\ValueObject\HouseholdId;
use App\Households\Domain\ValueObject\MemberId;

/**
 * Stub adapter for {@see MemberTransactionHistory} (LRA-45).
 *
 * The Transactions bounded context does not exist yet. Until it does,
 * this adapter produces a deterministic, hash-seeded synthetic history
 * per `(householdId, memberId)` pair so the Transaction History card is
 * reviewable end-to-end. The output is repeatable across processes
 * because the seed is derived purely from the member id, not from a
 * runtime random source.
 *
 * The row count for a given member falls in the range 0–25, derived
 * from `crc32($memberId->value) % 26`. Some member-id values therefore
 * naturally produce an empty history, which the empty-state test
 * exercises.
 *
 * Planned replacement: when the real Transactions context exists, a
 * sibling adapter (e.g. `LegacyDbMemberTransactionHistory`) under
 * `src/Households/Infrastructure/Acl/LegacyDb/` will implement the same
 * port and the container binding will switch to it (optionally behind an
 * env flag during cut-over). This stub stays in the codebase only as the
 * test/dev default until that swap happens.
 */
final class StubMemberTransactionHistory implements MemberTransactionHistory
{
    /**
     * Reference point the synthetic `occurredAt` series walks back from
     * one day at a time. Held as a constant string so the adapter has no
     * clock dependency — the stub is deterministic by design.
     */
    private const string REFERENCE_DATE = '2026-05-24';

    /** Upper bound on the synthetic row count produced per member. */
    private const int MAX_ROW_COUNT = 26;

    /** Hard cap mirrors the controller's pageSize validation. */
    private const int MAX_PAGE_SIZE = 50;

    /** @var list<string> */
    private const array PAYMENT_METHODS = ['Credit Card', 'Cash', 'Check', 'ACH'];

    public function page(
        HouseholdId $householdId,
        MemberId $memberId,
        int $page,
        int $pageSize,
    ): MemberTransactionPage {
        if ($page < 1) {
            throw new \InvalidArgumentException(
                sprintf('page must be >= 1, got %d.', $page),
            );
        }
        if ($pageSize < 1 || $pageSize > self::MAX_PAGE_SIZE) {
            throw new \InvalidArgumentException(
                sprintf(
                    'pageSize must be between 1 and %d, got %d.',
                    self::MAX_PAGE_SIZE,
                    $pageSize,
                ),
            );
        }

        $total = $this->rowCountFor($memberId);
        $offset = ($page - 1) * $pageSize;

        $items = [];
        $end = min($offset + $pageSize, $total);
        for ($i = $offset; $i < $end; $i++) {
            $items[] = $this->rowAt($memberId, $i);
        }

        $hasMore = $offset + $pageSize < $total;

        return new MemberTransactionPage($items, $page, $pageSize, $hasMore);
    }

    private function rowCountFor(MemberId $memberId): int
    {
        return crc32($memberId->value) % self::MAX_ROW_COUNT;
    }

    private function rowAt(MemberId $memberId, int $index): MemberTransactionRow
    {
        // Synthesize a deterministic transactionId per (memberId, index)
        // so re-rendering the same page yields stable values across
        // requests. The format is intentionally not a UUID — it carries
        // no semantic meaning and exists only so the test harness can
        // distinguish rows.
        $transactionId = sprintf('stub-%s-%04d', substr($memberId->value, 0, 8), $index);

        $occurredAt = (new \DateTimeImmutable(self::REFERENCE_DATE))
            ->modify(sprintf('-%d days', $index));

        $kinds = MemberTransactionKind::cases();
        $kind = $kinds[$index % count($kinds)];

        $description = sprintf('Stub transaction #%d', $index + 1);

        $amount = '$' . number_format(($index * 12.5) + 10, 2);

        // First 80% of rows are "Posted", remainder are "Pending".
        $total = $this->rowCountFor($memberId);
        $postedThreshold = (int) floor($total * 0.8);
        $status = $index < $postedThreshold ? 'Posted' : 'Pending';

        $paymentMethod = self::PAYMENT_METHODS[$index % count(self::PAYMENT_METHODS)];

        return new MemberTransactionRow(
            $transactionId,
            $occurredAt,
            $kind,
            $description,
            $amount,
            $status,
            $paymentMethod,
        );
    }
}
