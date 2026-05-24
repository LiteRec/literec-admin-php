<?php

declare(strict_types=1);

namespace App\Tests\Support\Fake;

use App\Households\Domain\IdentityGenerator;
use App\Households\Domain\ValueObject\HouseholdId;
use App\Households\Domain\ValueObject\MemberId;
use LogicException;

/**
 * Test double for the Households {@see IdentityGenerator} port.
 *
 * Returns identities from two independent queues so a single test can
 * pre-seed deterministic HouseholdId / MemberId values and assert on them.
 * Throws when either queue is exhausted so a test that accidentally
 * requests more ids than it set up fails loudly.
 */
final class HouseholdSequenceIdentityGenerator implements IdentityGenerator
{
    /** @var list<HouseholdId> */
    private array $householdIds;

    /** @var list<MemberId> */
    private array $memberIds;

    /**
     * @param list<HouseholdId> $householdIds
     * @param list<MemberId>    $memberIds
     */
    public function __construct(array $householdIds, array $memberIds)
    {
        $this->householdIds = $householdIds;
        $this->memberIds = $memberIds;
    }

    public function nextHouseholdId(): HouseholdId
    {
        if ($this->householdIds === []) {
            throw new LogicException('Household identity queue exhausted.');
        }

        return array_shift($this->householdIds);
    }

    public function nextMemberId(): MemberId
    {
        if ($this->memberIds === []) {
            throw new LogicException('Member identity queue exhausted.');
        }

        return array_shift($this->memberIds);
    }
}
