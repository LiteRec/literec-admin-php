<?php

declare(strict_types=1);

namespace App\Households\Domain;

use App\Households\Domain\ValueObject\MemberId;

/**
 * Domain port for scrubbing the staff-typed free text a member appears in
 * outside their own aggregate row — residency-change reasons, split/merge
 * lineage reasons — when that member is anonymized (LRA-212).
 *
 * Called synchronously from {@see \App\Households\Application\Command\AnonymizeMemberHandler}
 * inside the same `command.bus` `doctrine_transaction` as the aggregate
 * write, not from a post-commit event handler: the whole point of
 * anonymization is an atomic, complete scrub, so a mid-scrub failure must
 * roll back the member's `anonymized_at` write too rather than leave a
 * half-anonymized, unretryable record (a second anonymize attempt is
 * refused by {@see \App\Households\Domain\Exception\MemberIsAnonymized}).
 */
interface MemberFreeTextReasons
{
    public function scrubFor(MemberId $memberId): void;
}
