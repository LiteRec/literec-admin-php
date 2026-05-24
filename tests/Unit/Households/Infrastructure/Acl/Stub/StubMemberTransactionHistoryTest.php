<?php

declare(strict_types=1);

namespace App\Tests\Unit\Households\Infrastructure\Acl\Stub;

use App\Households\Application\Port\MemberTransactionPage;
use App\Households\Domain\ValueObject\HouseholdId;
use App\Households\Domain\ValueObject\MemberId;
use App\Households\Infrastructure\Acl\Stub\StubMemberTransactionHistory;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

/**
 * Pins down the stub adapter's deterministic seeding so the
 * Transaction History card's review flow has reproducible synthetic
 * data. The selected member-id fixtures were picked because their
 * `crc32($memberId) % 26` value lands at 0, 8, and 23 respectively —
 * giving an empty case, a medium case, and a case that paginates.
 */
#[Small]
final class StubMemberTransactionHistoryTest extends TestCase
{
    /** crc32(...) % 26 == 0 → produces an empty history. */
    private const string EMPTY_MEMBER_ID = '019571bf-5d55-7000-b500-000000000013';

    /** crc32(...) % 26 == 8 → produces 8 rows; fits in one default page. */
    private const string MEDIUM_MEMBER_ID = '019571bf-5d55-7000-b500-000000000010';

    /** crc32(...) % 26 == 23 → triggers multi-page behaviour at pageSize 10. */
    private const string LARGE_MEMBER_ID = '019571bf-5d55-7000-b500-000000000014';

    private const string HOUSEHOLD_ID = '019571bf-5d55-7000-b500-00000000ff01';

    private StubMemberTransactionHistory $adapter;
    private HouseholdId $householdId;

    protected function setUp(): void
    {
        $this->adapter = new StubMemberTransactionHistory();
        $this->householdId = HouseholdId::fromString(self::HOUSEHOLD_ID);
    }

    #[Test]
    #[TestDox('Returns the same first row for the same (householdId, memberId) across calls.')]
    public function is_deterministic_across_calls(): void
    {
        $memberId = MemberId::fromString(self::MEDIUM_MEMBER_ID);

        $first = $this->adapter->page($this->householdId, $memberId, 1, 20);
        $second = $this->adapter->page($this->householdId, $memberId, 1, 20);

        self::assertNotEmpty($first->items);
        self::assertSame($first->items[0]->transactionId, $second->items[0]->transactionId);
        self::assertSame(
            $first->items[0]->occurredAt->format(\DateTimeInterface::ATOM),
            $second->items[0]->occurredAt->format(\DateTimeInterface::ATOM),
        );
        self::assertSame($first->items[0]->amount, $second->items[0]->amount);
    }

    #[Test]
    #[TestDox('Page 1 returns at most pageSize rows; page 2 starts at the right offset.')]
    public function pagination_respects_page_size(): void
    {
        $memberId = MemberId::fromString(self::LARGE_MEMBER_ID);
        $pageSize = 10;

        $first = $this->adapter->page($this->householdId, $memberId, 1, $pageSize);
        $second = $this->adapter->page($this->householdId, $memberId, 2, $pageSize);

        self::assertCount($pageSize, $first->items);
        self::assertCount($pageSize, $second->items);

        // The first row of page 2 must equal the row at index = pageSize
        // when the same member is paged at pageSize == 1 (offset proof).
        $offsetProof = $this->adapter->page($this->householdId, $memberId, $pageSize + 1, 1);
        self::assertCount(1, $offsetProof->items);
        self::assertSame(
            $second->items[0]->transactionId,
            $offsetProof->items[0]->transactionId,
        );
    }

    #[Test]
    #[TestDox('hasMore is true while pages remain and false on the final page.')]
    public function has_more_returns_true_when_more_pages_exist_else_false(): void
    {
        $memberId = MemberId::fromString(self::LARGE_MEMBER_ID);

        $firstOfThree = $this->adapter->page($this->householdId, $memberId, 1, 10);
        self::assertTrue($firstOfThree->hasMore, 'Page 1 of 3 must report more pages.');

        $secondOfThree = $this->adapter->page($this->householdId, $memberId, 2, 10);
        self::assertTrue($secondOfThree->hasMore, 'Page 2 of 3 must report more pages.');

        $thirdOfThree = $this->adapter->page($this->householdId, $memberId, 3, 10);
        self::assertFalse($thirdOfThree->hasMore, 'Final page must not report more pages.');
        self::assertLessThanOrEqual(10, count($thirdOfThree->items));
    }

    #[Test]
    #[TestDox('Some members deterministically have an empty history.')]
    public function empty_for_certain_members(): void
    {
        $memberId = MemberId::fromString(self::EMPTY_MEMBER_ID);

        $page = $this->adapter->page($this->householdId, $memberId, 1, 20);

        self::assertInstanceOf(MemberTransactionPage::class, $page);
        self::assertSame([], $page->items);
        self::assertFalse($page->hasMore);
    }

    #[Test]
    #[TestDox('Rejects pageSize above the documented hard cap with an InvalidArgumentException.')]
    public function rejects_page_size_above_cap(): void
    {
        $memberId = MemberId::fromString(self::MEDIUM_MEMBER_ID);

        $this->expectException(\InvalidArgumentException::class);
        $this->adapter->page($this->householdId, $memberId, 1, 999);
    }

    #[Test]
    #[TestDox('Rejects a page number below 1 with an InvalidArgumentException.')]
    public function rejects_page_below_one(): void
    {
        $memberId = MemberId::fromString(self::MEDIUM_MEMBER_ID);

        $this->expectException(\InvalidArgumentException::class);
        $this->adapter->page($this->householdId, $memberId, 0, 20);
    }
}
