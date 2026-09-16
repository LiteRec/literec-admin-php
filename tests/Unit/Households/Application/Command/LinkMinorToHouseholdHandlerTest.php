<?php

declare(strict_types=1);

namespace App\Tests\Unit\Households\Application\Command;

use App\Households\Application\Command\LinkMinorToHousehold;
use App\Households\Application\Command\LinkMinorToHouseholdHandler;
use App\Households\Domain\Event\MemberSharedWithHousehold;
use App\Households\Domain\Exception\CannotShareWithHomeHousehold;
use App\Households\Domain\Exception\HouseholdAlreadyLinked;
use App\Households\Domain\Exception\HouseholdNotFound;
use App\Households\Domain\Exception\MemberAlreadyMerged;
use App\Households\Domain\Exception\MemberNotAMinor;
use App\Households\Domain\Household;
use App\Households\Domain\ValueObject\Address;
use App\Households\Domain\ValueObject\DateOfBirth;
use App\Households\Domain\ValueObject\Gender;
use App\Households\Domain\ValueObject\HouseholdId;
use App\Households\Domain\ValueObject\HouseholdName;
use App\Households\Domain\ValueObject\MemberCode;
use App\Households\Domain\ValueObject\MemberContact;
use App\Households\Domain\ValueObject\MemberId;
use App\Households\Domain\ValueObject\MemberProfile;
use App\Households\Domain\ValueObject\PersonName;
use App\Households\Domain\ValueObject\ResidencyStatus;
use App\Households\Infrastructure\Persistence\InMemory\InMemoryHouseholds;
use App\Shared\Domain\ValueObject\EmailAddress;
use App\Shared\Domain\ValueObject\PhoneNumber;
use App\Tests\Support\Fake\RecordingMessageBus;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[Small]
final class LinkMinorToHouseholdHandlerTest extends TestCase
{
    private const string HOME_HOUSEHOLD_ID = '019571bf-5d54-7000-b500-000000000e01';
    private const string PRIMARY_ID        = '019571bf-5d54-7000-b500-000000000e02';
    private const string MINOR_ID          = '019571bf-5d54-7000-b500-000000000e03';
    private const string TARGET_HOUSEHOLD_ID = '019571bf-5d54-7000-b500-000000000e04';
    private const string UNKNOWN_HOUSEHOLD_ID = '019571bf-5d54-7000-b500-0000000000fe';

    private MockClock $clock;
    private InMemoryHouseholds $households;
    private RecordingMessageBus $eventBus;
    private LinkMinorToHouseholdHandler $handler;

    protected function setUp(): void
    {
        $this->clock = new MockClock(new DateTimeImmutable('2026-05-24 12:00:00'));
        $this->households = new InMemoryHouseholds();

        $home = $this->seedHomeHousehold();
        $home->releaseEvents();
        $this->households->save($home);

        $target = $this->seedTargetHousehold();
        $target->releaseEvents();
        $this->households->save($target);

        $this->eventBus = new RecordingMessageBus();
        $this->handler = new LinkMinorToHouseholdHandler($this->households, $this->clock, $this->eventBus);
    }

    #[Test]
    #[TestDox('Shares the minor with the target household and dispatches exactly one MemberSharedWithHousehold.')]
    public function happy_path_shares_minor_and_dispatches_event(): void
    {
        ($this->handler)(new LinkMinorToHousehold(self::TARGET_HOUSEHOLD_ID, self::MINOR_ID));

        $home = $this->households->findById(HouseholdId::fromString(self::HOME_HOUSEHOLD_ID));
        $minor = $this->memberById($home, self::MINOR_ID);
        self::assertTrue($minor->householdLinks()->includes(HouseholdId::fromString(self::TARGET_HOUSEHOLD_ID)));

        $messages = $this->eventBus->dispatchedMessages();
        self::assertCount(1, $messages);
        self::assertInstanceOf(MemberSharedWithHousehold::class, $messages[0]);
    }

    #[Test]
    #[TestDox('Throws HouseholdNotFound when the target household does not exist.')]
    public function rejects_unknown_target_household(): void
    {
        $this->expectException(HouseholdNotFound::class);

        ($this->handler)(new LinkMinorToHousehold(self::UNKNOWN_HOUSEHOLD_ID, self::MINOR_ID));
    }

    #[Test]
    #[TestDox('Throws MemberNotAMinor for an adult member.')]
    public function rejects_adult_member(): void
    {
        $this->expectException(MemberNotAMinor::class);

        ($this->handler)(new LinkMinorToHousehold(self::TARGET_HOUSEHOLD_ID, self::PRIMARY_ID));
    }

    #[Test]
    #[TestDox('Throws CannotShareWithHomeHousehold when the target is the member\'s own home household.')]
    public function rejects_home_household_as_target(): void
    {
        $this->expectException(CannotShareWithHomeHousehold::class);

        ($this->handler)(new LinkMinorToHousehold(self::HOME_HOUSEHOLD_ID, self::MINOR_ID));
    }

    #[Test]
    #[TestDox('Throws HouseholdAlreadyLinked on a duplicate link and leaves a single event from the first call.')]
    public function rejects_duplicate_link(): void
    {
        ($this->handler)(new LinkMinorToHousehold(self::TARGET_HOUSEHOLD_ID, self::MINOR_ID));

        try {
            ($this->handler)(new LinkMinorToHousehold(self::TARGET_HOUSEHOLD_ID, self::MINOR_ID));
            self::fail('Expected HouseholdAlreadyLinked to be thrown.');
        } catch (HouseholdAlreadyLinked) {
            // expected
        }

        self::assertCount(1, $this->eventBus->dispatchedMessages());
    }

    #[Test]
    #[TestDox('Locks and re-asserts the member is not merged, with the home household and member id, before saving.')]
    public function locks_unmerged_member_before_saving(): void
    {
        $spy = new RecordsLockUnmergedMemberCalls($this->households);
        $handler = new LinkMinorToHouseholdHandler($spy, $this->clock, $this->eventBus);

        $handler(new LinkMinorToHousehold(self::TARGET_HOUSEHOLD_ID, self::MINOR_ID));

        self::assertCount(1, $spy->lockCalls);
        self::assertTrue($spy->lockCalls[0]['householdId']->equals(HouseholdId::fromString(self::HOME_HOUSEHOLD_ID)));
        self::assertTrue($spy->lockCalls[0]['memberId']->equals(MemberId::fromString(self::MINOR_ID)));
    }

    #[Test]
    #[TestDox('Propagates MemberAlreadyMerged and never saves when the member was merged concurrently.')]
    public function propagates_lock_failure_and_never_saves(): void
    {
        $spy = new ThrowsOnLockHouseholds($this->households);
        $handler = new LinkMinorToHouseholdHandler($spy, $this->clock, $this->eventBus);

        $this->expectException(MemberAlreadyMerged::class);

        try {
            $handler(new LinkMinorToHousehold(self::TARGET_HOUSEHOLD_ID, self::MINOR_ID));
        } finally {
            self::assertSame(0, $spy->saveCalls);
        }
    }

    private function memberById(Household $household, string $memberId): \App\Households\Domain\HouseholdMember
    {
        $needle = MemberId::fromString($memberId);
        foreach ($household->members() as $member) {
            if ($member->id()->equals($needle)) {
                return $member;
            }
        }
        self::fail(sprintf('Member %s not found in household.', $memberId));
    }

    private function seedHomeHousehold(): Household
    {
        $address = Address::of('100 Main St', null, 'Seattle', 'WA', '98101', 'US');

        $home = Household::register(
            HouseholdId::fromString(self::HOME_HOUSEHOLD_ID),
            HouseholdName::of('Smith Family'),
            $address,
            MemberId::fromString(self::PRIMARY_ID),
            MemberCode::of('M000500'),
            MemberProfile::of(
                PersonName::of('Alice', 'Smith'),
                DateOfBirth::of(new DateTimeImmutable('1990-01-01'), $this->clock),
                Gender::Female,
            ),
            MemberContact::of(EmailAddress::of('alice@example.com'), PhoneNumber::of('5550001')),
            ResidencyStatus::Resident,
            $this->clock,
        );

        $home->addMember(
            MemberId::fromString(self::MINOR_ID),
            MemberCode::of('M000501'),
            MemberProfile::of(
                PersonName::of('Timmy', 'Smith'),
                DateOfBirth::of(new DateTimeImmutable('2015-01-01'), $this->clock),
                Gender::Male,
            ),
            MemberContact::none(),
            ResidencyStatus::Resident,
            false,
            $this->clock,
        );

        return $home;
    }

    private function seedTargetHousehold(): Household
    {
        $address = Address::of('200 Oak Ave', null, 'Portland', 'OR', '97201', 'US');

        return Household::register(
            HouseholdId::fromString(self::TARGET_HOUSEHOLD_ID),
            HouseholdName::of('Jones Family'),
            $address,
            MemberId::fromString('019571bf-5d54-7000-b500-000000000e05'),
            MemberCode::of('M000502'),
            MemberProfile::of(
                PersonName::of('Bob', 'Jones'),
                DateOfBirth::of(new DateTimeImmutable('1978-01-01'), $this->clock),
                Gender::Male,
            ),
            MemberContact::of(EmailAddress::of('bob@example.com'), null),
            ResidencyStatus::Resident,
            $this->clock,
        );
    }
}
