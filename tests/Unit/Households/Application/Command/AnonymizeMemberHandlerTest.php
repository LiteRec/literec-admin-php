<?php

declare(strict_types=1);

namespace App\Tests\Unit\Households\Application\Command;

use App\Households\Application\Command\AnonymizeMember;
use App\Households\Application\Command\AnonymizeMemberHandler;
use App\Households\Domain\Event\MemberAnonymized;
use App\Households\Domain\Exception\HouseholdNotFound;
use App\Households\Domain\Exception\MemberIsAnonymized;
use App\Households\Domain\Exception\MemberNotFound;
use App\Households\Domain\ValueObject\HouseholdId;
use App\Households\Domain\ValueObject\MemberCode;
use App\Households\Domain\ValueObject\MemberId;
use App\Households\Infrastructure\Persistence\InMemory\InMemoryHouseholds;
use App\Households\Infrastructure\Persistence\InMemory\InMemoryMemberFreeTextReasons;
use App\Tests\Support\Fake\RecordingMessageBus;
use App\Tests\Support\Trait\SeedsAliceSmithHousehold;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[Small]
final class AnonymizeMemberHandlerTest extends TestCase
{
    use SeedsAliceSmithHousehold;

    private const string HOUSEHOLD_ID = '019571bf-5d55-7000-b500-000000000e01';
    private const string PRIMARY_ID   = '019571bf-5d55-7000-b500-000000000e02';
    private const string PRIMARY_CODE = 'M000500';
    private const string UNKNOWN_ID   = '019571bf-5d55-7000-b500-0000000000fe';
    private const string UNKNOWN_HOUSEHOLD_ID = '019571bf-5d55-7000-b500-0000000000ff';

    private MockClock $clock;
    private InMemoryHouseholds $households;
    private InMemoryMemberFreeTextReasons $freeTextReasons;
    private RecordingMessageBus $eventBus;
    private AnonymizeMemberHandler $handler;

    protected function setUp(): void
    {
        $this->clock = new MockClock(new DateTimeImmutable('2026-05-24 12:00:00'));
        $this->households = new InMemoryHouseholds();
        $seed = $this->seedAliceSmithHousehold(
            HouseholdId::fromString(self::HOUSEHOLD_ID),
            MemberId::fromString(self::PRIMARY_ID),
            MemberCode::of(self::PRIMARY_CODE),
            $this->clock,
        );
        // Drain registration events so each test sees only what the handler
        // under test publishes.
        $seed->releaseEvents();
        $this->households->save($seed);

        $this->freeTextReasons = new InMemoryMemberFreeTextReasons();
        $this->eventBus = new RecordingMessageBus();
        $this->handler = new AnonymizeMemberHandler(
            $this->households,
            $this->freeTextReasons,
            $this->clock,
            $this->eventBus,
        );
    }

    #[Test]
    #[TestDox('Anonymizes an active member and publishes exactly one MemberAnonymized.')]
    public function happy_path_anonymizes_member_and_dispatches_event(): void
    {
        ($this->handler)(new AnonymizeMember(
            householdId: self::HOUSEHOLD_ID,
            memberId: self::PRIMARY_ID,
        ));

        $stored = $this->households->findById(HouseholdId::fromString(self::HOUSEHOLD_ID));
        $member = $this->memberById($stored, self::PRIMARY_ID);
        self::assertTrue($member->isAnonymized());
        self::assertFalse($member->isActive());
        self::assertSame('Anonymized', $member->name()->firstName);

        $messages = $this->eventBus->dispatchedMessages();
        self::assertCount(1, $messages);
        self::assertInstanceOf(MemberAnonymized::class, $messages[0]);

        // Scrubbed synchronously by the handler itself (LRA-212), not left
        // to a post-commit event subscriber.
        self::assertCount(1, $this->freeTextReasons->scrubbedMemberIds());
        self::assertTrue($this->freeTextReasons->scrubbedMemberIds()[0]->equals(
            MemberId::fromString(self::PRIMARY_ID),
        ));
    }

    #[Test]
    #[TestDox('Anonymizing an already-anonymized member throws MemberIsAnonymized.')]
    public function second_dispatch_throws_member_is_anonymized(): void
    {
        ($this->handler)(new AnonymizeMember(
            householdId: self::HOUSEHOLD_ID,
            memberId: self::PRIMARY_ID,
        ));

        $this->expectException(MemberIsAnonymized::class);

        ($this->handler)(new AnonymizeMember(
            householdId: self::HOUSEHOLD_ID,
            memberId: self::PRIMARY_ID,
        ));
    }

    #[Test]
    #[TestDox('Throws MemberNotFound when the memberId does not exist in the household.')]
    public function rejects_unknown_member(): void
    {
        $this->expectException(MemberNotFound::class);

        ($this->handler)(new AnonymizeMember(
            householdId: self::HOUSEHOLD_ID,
            memberId: self::UNKNOWN_ID,
        ));
    }

    #[Test]
    #[TestDox('Throws HouseholdNotFound when the householdId does not exist.')]
    public function rejects_unknown_household(): void
    {
        $this->expectException(HouseholdNotFound::class);

        ($this->handler)(new AnonymizeMember(
            householdId: self::UNKNOWN_HOUSEHOLD_ID,
            memberId: self::PRIMARY_ID,
        ));
    }
}
