<?php

declare(strict_types=1);

namespace App\Households\Application\Port;

/**
 * Read-side page DTO for the Member Transaction History card (LRA-45).
 *
 * Single-call port shape: the adapter returns both the slice of rows
 * for the requested page and a `hasMore` flag, so the controller does
 * not have to make a second call (or over-fetch by one row) to decide
 * whether to render the "Load more" button.
 */
final readonly class MemberTransactionPage
{
    /**
     * @param list<MemberTransactionRow> $items
     */
    public function __construct(
        public array $items,
        public int $page,
        public int $pageSize,
        public bool $hasMore,
    ) {
    }
}
