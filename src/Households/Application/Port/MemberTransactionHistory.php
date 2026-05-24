<?php

declare(strict_types=1);

namespace App\Households\Application\Port;

use App\Households\Domain\ValueObject\HouseholdId;
use App\Households\Domain\ValueObject\MemberId;

/**
 * Read-side Anti-Corruption Layer port for member transaction history
 * (LRA-45).
 *
 * The Transactions bounded context does not exist yet; this port lets
 * the Households member-detail page render a Transaction History card
 * without taking a dependency on a context that has not been modelled.
 * When the real Transactions context lands, a new adapter under
 * `Infrastructure/Acl/` will translate its read model into the
 * {@see MemberTransactionPage} shape and be swapped in via container
 * binding — no controller or template change required.
 *
 * Until then, the production binding points at
 * {@see \App\Households\Infrastructure\Acl\Stub\StubMemberTransactionHistory}.
 *
 * The single `page()` method returns both the page slice and a
 * `hasMore` flag, so the controller can decide whether to render the
 * "Load more" button without forcing the adapter to over-fetch by one
 * row or to compute the total count separately.
 */
interface MemberTransactionHistory
{
    /**
     * @throws \InvalidArgumentException when `$page` is less than 1, or
     *         when `$pageSize` is outside the inclusive range 1..50.
     *         Callers (the HTTP adapter) translate this to HTTP 400.
     */
    public function page(
        HouseholdId $householdId,
        MemberId $memberId,
        int $page,
        int $pageSize,
    ): MemberTransactionPage;
}
