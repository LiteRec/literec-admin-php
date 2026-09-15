<?php

declare(strict_types=1);

namespace App\Households\Infrastructure\Persistence\InMemory;

use App\Households\Domain\MemberFreeTextReasons;
use App\Households\Domain\ValueObject\MemberId;

/**
 * In-memory-for-tests adapter for the {@see MemberFreeTextReasons} port
 * (LRA-212). Records every scrubbed member id so unit tests can assert
 * {@see \App\Households\Application\Command\AnonymizeMemberHandler} calls
 * it, without touching the real `household_residency_history` /
 * `household_member_lineage` tables.
 */
final class InMemoryMemberFreeTextReasons implements MemberFreeTextReasons
{
    /** @var list<MemberId> */
    private array $scrubbedMemberIds = [];

    public function scrubFor(MemberId $memberId): void
    {
        $this->scrubbedMemberIds[] = $memberId;
    }

    /**
     * @return list<MemberId>
     */
    public function scrubbedMemberIds(): array
    {
        return $this->scrubbedMemberIds;
    }
}
