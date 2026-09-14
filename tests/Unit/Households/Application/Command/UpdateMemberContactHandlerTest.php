<?php

declare(strict_types=1);

namespace App\Tests\Unit\Households\Application\Command;

use App\Households\Application\Command\UpdateMemberContact;
use App\Households\Application\Command\UpdateMemberContactHandler;
use App\Households\Domain\Event\MemberContactUpdated;
use App\Households\Domain\Exception\MemberNotFound;
use App\Households\Domain\Household;
use App\Households\Domain\HouseholdMember;
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
use App\Shared\Domain\Exception\InvalidEmailAddress;
use App\Shared\Domain\Exception\InvalidPhoneNumber;
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
final class UpdateMemberContactHandlerTest extends TestCase
{
    private const string HOUSEHOLD_ID = '019571bf-5d54-7000-b500-000000000d01';
    private const string PRIMARY_ID   = '019571bf-5d54-7000-b500-000000000d02';
    private const string PRIMARY_CODE = 'M000400';
    private const string UNKNOWN_ID   = '019571bf-5d54-7000-b500-0000000000fe';

    private MockClock $clock;
    private InMemoryHouseholds $households;
    private RecordingMessageBus $eventBus;
    private UpdateMemberContactHandler $handler;

    protected function setUp(): void
    {
        $this->clock = new MockClock(new DateTimeImmutable('2026-05-24 12:00:00'));
        $this->households = new InMemoryHouseholds();
        $seed = $this->seedHousehold();
        // Drain registration events so each test sees only what the handler
        // under test publishes.
        $seed->releaseEvents();
        $this->households->save($seed);

        $this->eventBus = new RecordingMessageBus();
        $this->handler = new UpdateMemberContactHandler(
            $this->households,
            $this->clock,
            $this->eventBus,
        );
    }

    #[Test]
    #[TestDox('Updates both channels and dispatches exactly one MemberContactUpdated.')]
    public function happy_path_updates_both_channels_and_dispatches_event(): void
    {
        $command = new UpdateMemberContact(
            householdId: self::HOUSEHOLD_ID,
            memberId: self::PRIMARY_ID,
            email: 'alicia.new@example.com',
            phone: '5559999',
        );

        ($this->handler)($command);

        $member = $this->memberById($this->reload(), self::PRIMARY_ID);
        self::assertSame('alicia.new@example.com', (string) $member->email());
        self::assertSame('5559999', (string) $member->phone());

        $messages = $this->eventBus->dispatchedMessages();
        self::assertCount(1, $messages);
        self::assertInstanceOf(MemberContactUpdated::class, $messages[0]);
    }

    #[Test]
    #[TestDox('null/null clears both channels and dispatches the event.')]
    public function null_values_clear_both_channels_and_dispatch_event(): void
    {
        $command = new UpdateMemberContact(
            householdId: self::HOUSEHOLD_ID,
            memberId: self::PRIMARY_ID,
            email: null,
            phone: null,
        );

        ($this->handler)($command);

        $member = $this->memberById($this->reload(), self::PRIMARY_ID);
        self::assertNull($member->email());
        self::assertNull($member->phone());

        $messages = $this->eventBus->dispatchedMessages();
        self::assertCount(1, $messages);
        self::assertInstanceOf(MemberContactUpdated::class, $messages[0]);
    }

    #[Test]
    #[TestDox('Submitting the unchanged values is a no-op: no event is dispatched.')]
    public function no_op_when_values_are_unchanged(): void
    {
        $command = new UpdateMemberContact(
            householdId: self::HOUSEHOLD_ID,
            memberId: self::PRIMARY_ID,
            email: 'alice@example.com',
            phone: '5550001',
        );

        ($this->handler)($command);

        self::assertSame([], $this->eventBus->dispatchedMessages());
    }

    #[Test]
    #[TestDox('Throws MemberNotFound when the memberId does not exist in the household.')]
    public function rejects_unknown_member(): void
    {
        $command = new UpdateMemberContact(
            householdId: self::HOUSEHOLD_ID,
            memberId: self::UNKNOWN_ID,
            email: 'ghost@example.com',
            phone: null,
        );

        $this->expectException(MemberNotFound::class);

        ($this->handler)($command);
    }

    #[Test]
    #[TestDox('A malformed email throws InvalidEmailAddress and leaves the stored member untouched.')]
    public function rejects_malformed_email_and_leaves_member_untouched(): void
    {
        $command = new UpdateMemberContact(
            householdId: self::HOUSEHOLD_ID,
            memberId: self::PRIMARY_ID,
            email: 'not-an-email',
            phone: null,
        );

        try {
            ($this->handler)($command);
            self::fail('Expected InvalidEmailAddress to be thrown.');
        } catch (InvalidEmailAddress) {
            // expected
        }

        $member = $this->memberById($this->reload(), self::PRIMARY_ID);
        self::assertSame('alice@example.com', (string) $member->email());
        self::assertSame([], $this->eventBus->dispatchedMessages());
    }

    #[Test]
    #[TestDox('Illegal phone characters throw InvalidPhoneNumber and leave the stored member untouched.')]
    public function rejects_illegal_phone_characters_and_leaves_member_untouched(): void
    {
        $command = new UpdateMemberContact(
            householdId: self::HOUSEHOLD_ID,
            memberId: self::PRIMARY_ID,
            email: null,
            phone: 'not-a-phone',
        );

        try {
            ($this->handler)($command);
            self::fail('Expected InvalidPhoneNumber to be thrown.');
        } catch (InvalidPhoneNumber) {
            // expected
        }

        $member = $this->memberById($this->reload(), self::PRIMARY_ID);
        self::assertSame('5550001', (string) $member->phone());
        self::assertSame([], $this->eventBus->dispatchedMessages());
    }

    private function reload(): Household
    {
        return $this->households->findById(HouseholdId::fromString(self::HOUSEHOLD_ID));
    }

    private function memberById(Household $household, string $memberId): HouseholdMember
    {
        $needle = MemberId::fromString($memberId);
        $matches = array_values(array_filter(
            $household->members(),
            static fn(HouseholdMember $member): bool => $member->id()->equals($needle),
        ));

        self::assertNotEmpty($matches, sprintf('Member %s not found in household.', $memberId));

        return $matches[0];
    }

    private function seedHousehold(): Household
    {
        $address = Address::of('100 Main St', null, 'Seattle', 'WA', '98101', 'US');
        $primaryMemberName = PersonName::of('Alice', 'Smith');
        $primaryMemberDob = DateOfBirth::of(new DateTimeImmutable('1990-01-01'), $this->clock);

        return Household::register(
            HouseholdId::fromString(self::HOUSEHOLD_ID),
            HouseholdName::of('Smith Family'),
            $address,
            MemberId::fromString(self::PRIMARY_ID),
            MemberCode::of(self::PRIMARY_CODE),
            $primaryMemberName,
            $primaryMemberDob,
            Gender::Female,
            EmailAddress::of('alice@example.com'),
            PhoneNumber::of('5550001'),
            ResidencyStatus::Resident,
            $this->clock,
        );
    }
}
