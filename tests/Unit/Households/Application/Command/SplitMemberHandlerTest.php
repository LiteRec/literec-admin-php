<?php

declare(strict_types=1);

namespace App\Tests\Unit\Households\Application\Command;

use App\Households\Application\Command\SplitMember;
use App\Households\Application\Command\SplitMemberHandler;
use App\Households\Domain\Event\MemberAddedToHousehold;
use App\Households\Domain\Event\MemberSplitOff;
use App\Households\Domain\Exception\MemberAlreadyMerged;
use App\Households\Domain\Exception\SplitSelectionEmpty;
use App\Households\Domain\Household;
use App\Households\Domain\ValueObject\Address;
use App\Households\Domain\ValueObject\DateOfBirth;
use App\Households\Domain\ValueObject\Gender;
use App\Households\Domain\ValueObject\HouseholdId;
use App\Households\Domain\ValueObject\HouseholdName;
use App\Households\Domain\ValueObject\MemberCode;
use App\Households\Domain\ValueObject\MemberId;
use App\Households\Domain\ValueObject\PersonName;
use App\Households\Domain\ValueObject\ResidencyStatus;
use App\Households\Infrastructure\Persistence\InMemory\InMemoryHouseholds;
use App\Households\Infrastructure\Persistence\InMemory\InMemoryMemberCodeAllocator;
use App\Shared\Domain\ValueObject\EmailAddress;
use App\Shared\Domain\ValueObject\PhoneNumber;
use App\Tests\Support\Fake\HouseholdSequenceIdentityGenerator;
use App\Tests\Support\Fake\RecordingMessageBus;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;

#[Small]
final class SplitMemberHandlerTest extends TestCase
{
    private const string HOUSEHOLD_ID  = '019571bf-5d51-7000-b500-000000000c01';
    private const string SOURCE_ID     = '019571bf-5d51-7000-b500-000000000c02';
    private const string NEW_MEMBER_ID = '019571bf-5d51-7000-b500-000000000c03';

    private MockClock $clock;
    private InMemoryHouseholds $households;
    private RecordingMessageBus $eventBus;
    private SplitMemberHandler $handler;

    protected function setUp(): void
    {
        $this->clock = new MockClock(new DateTimeImmutable('2026-05-24 12:00:00'));
        $this->households = new InMemoryHouseholds();
        $seed = $this->seedHousehold();
        $seed->releaseEvents();
        $this->households->save($seed);

        $this->eventBus = new RecordingMessageBus();
        $ids = new HouseholdSequenceIdentityGenerator([], [MemberId::fromString(self::NEW_MEMBER_ID)]);

        $this->handler = new SplitMemberHandler(
            $this->households,
            $ids,
            new InMemoryMemberCodeAllocator(),
            $this->clock,
            $this->eventBus,
        );
    }

    #[Test]
    #[TestDox('Splits off a new member, returns its MemberId, and dispatches both events post-commit.')]
    public function happy_path_splits_member_and_dispatches_events(): void
    {
        $command = $this->validCommand();

        $newMemberId = ($this->handler)($command);

        self::assertSame(self::NEW_MEMBER_ID, $newMemberId->value);

        $stored = $this->households->findById(HouseholdId::fromString(self::HOUSEHOLD_ID));
        self::assertCount(2, $stored->members());

        $messages = $this->eventBus->dispatchedMessages();
        self::assertCount(2, $messages);
        self::assertInstanceOf(MemberAddedToHousehold::class, $messages[0]);
        self::assertInstanceOf(MemberSplitOff::class, $messages[1]);
        self::assertSame(['txn-1', 'txn-2'], $messages[1]->transactions->toStrings());

        foreach ($this->eventBus->envelopes() as $envelope) {
            self::assertNotNull($envelope->last(DispatchAfterCurrentBusStamp::class));
        }
    }

    #[Test]
    #[TestDox('Throws SplitSelectionEmpty when no transaction ids are supplied.')]
    public function rejects_empty_transaction_selection(): void
    {
        $command = $this->validCommand(transactionIds: []);

        $this->expectException(SplitSelectionEmpty::class);

        ($this->handler)($command);
    }

    #[Test]
    #[TestDox('Locks and re-asserts the source is not merged, with the source member id, before saving.')]
    public function locks_unmerged_source_before_saving(): void
    {
        $spy = new RecordsLockUnmergedMemberCalls($this->households);
        $handler = new SplitMemberHandler(
            $spy,
            new HouseholdSequenceIdentityGenerator([], [MemberId::fromString(self::NEW_MEMBER_ID)]),
            new InMemoryMemberCodeAllocator(),
            $this->clock,
            $this->eventBus,
        );

        $handler($this->validCommand());

        self::assertCount(1, $spy->lockCalls);
        self::assertTrue($spy->lockCalls[0]['householdId']->equals(HouseholdId::fromString(self::HOUSEHOLD_ID)));
        self::assertTrue($spy->lockCalls[0]['memberId']->equals(MemberId::fromString(self::SOURCE_ID)));
    }

    #[Test]
    #[TestDox('Propagates MemberAlreadyMerged and never saves when the source was merged concurrently.')]
    public function propagates_lock_failure_and_never_saves(): void
    {
        $spy = new ThrowsOnLockHouseholds($this->households);
        $handler = new SplitMemberHandler(
            $spy,
            new HouseholdSequenceIdentityGenerator([], [MemberId::fromString(self::NEW_MEMBER_ID)]),
            new InMemoryMemberCodeAllocator(),
            $this->clock,
            $this->eventBus,
        );

        $this->expectException(MemberAlreadyMerged::class);

        try {
            $handler($this->validCommand());
        } finally {
            self::assertSame(0, $spy->saveCalls);
        }
    }

    /**
     * @param ?list<string> $transactionIds
     */
    private function validCommand(?array $transactionIds = null): SplitMember
    {
        return new SplitMember(
            householdId: self::HOUSEHOLD_ID,
            sourceMemberId: self::SOURCE_ID,
            firstName: 'Bob',
            lastName: 'Smith',
            middleName: null,
            suffix: null,
            email: 'bob@example.com',
            phone: '5550002',
            transactionIds: $transactionIds ?? ['txn-1', 'txn-2'],
            reason: 'Two people share one record',
            memberCode: null,
        );
    }

    private function seedHousehold(): Household
    {
        return Household::register(
            HouseholdId::fromString(self::HOUSEHOLD_ID),
            HouseholdName::of('Smith Family'),
            Address::of('100 Main St', null, 'Seattle', 'WA', '98101', 'US'),
            MemberId::fromString(self::SOURCE_ID),
            MemberCode::of('M000100'),
            PersonName::of('Alice', 'Smith'),
            DateOfBirth::of(new DateTimeImmutable('1990-01-01'), $this->clock),
            Gender::Female,
            EmailAddress::of('alice@example.com'),
            null,
            ResidencyStatus::Resident,
            $this->clock,
        );
    }
}
