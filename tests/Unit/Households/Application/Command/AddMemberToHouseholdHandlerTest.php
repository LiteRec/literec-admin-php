<?php

declare(strict_types=1);

namespace App\Tests\Unit\Households\Application\Command;

use App\Households\Application\Command\AddMemberToHousehold;
use App\Households\Application\Command\AddMemberToHouseholdHandler;
use App\Households\Domain\Event\MemberAddedToHousehold;
use App\Households\Domain\Exception\DuplicateMemberCode;
use App\Households\Domain\Household;
use App\Households\Domain\ValueObject\Address;
use App\Households\Domain\ValueObject\DateOfBirth;
use App\Households\Domain\ValueObject\EmailAddress;
use App\Households\Domain\ValueObject\Gender;
use App\Households\Domain\ValueObject\HouseholdId;
use App\Households\Domain\ValueObject\HouseholdName;
use App\Households\Domain\ValueObject\MemberCode;
use App\Households\Domain\ValueObject\MemberId;
use App\Households\Domain\ValueObject\PersonName;
use App\Households\Domain\ValueObject\ResidencyStatus;
use App\Households\Infrastructure\Persistence\InMemory\InMemoryHouseholds;
use App\Households\Infrastructure\Persistence\InMemory\InMemoryMemberCodeAllocator;
use App\Tests\Support\Fake\HouseholdSequenceIdentityGenerator;
use App\Tests\Support\Fake\RecordingMessageBus;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[Small]
final class AddMemberToHouseholdHandlerTest extends TestCase
{
    private const string HOUSEHOLD_ID    = '019571bf-5d51-7000-b500-000000000b01';
    private const string PRIMARY_ID      = '019571bf-5d51-7000-b500-000000000b02';
    private const string EXISTING_CODE   = 'M000100';
    private const string NEW_MEMBER_ID   = '019571bf-5d51-7000-b500-000000000b03';

    private MockClock $clock;
    private InMemoryHouseholds $households;
    private RecordingMessageBus $eventBus;
    private AddMemberToHouseholdHandler $handler;

    protected function setUp(): void
    {
        $this->clock = new MockClock(new DateTimeImmutable('2026-05-24 12:00:00'));
        $this->households = new InMemoryHouseholds();
        $seed = $this->seedHousehold();
        // Drain the registration events so the test only sees what the
        // handler-under-test publishes for the AddMemberToHousehold call.
        $seed->releaseEvents();
        $this->households->save($seed);

        $this->eventBus = new RecordingMessageBus();
        $ids = new HouseholdSequenceIdentityGenerator(
            [],
            [MemberId::fromString(self::NEW_MEMBER_ID)],
        );

        $this->handler = new AddMemberToHouseholdHandler(
            $this->households,
            $ids,
            new InMemoryMemberCodeAllocator(),
            $this->clock,
            $this->eventBus,
        );
    }

    #[Test]
    #[TestDox('Adds a new member, returns the MemberId, and publishes MemberAddedToHousehold.')]
    public function happy_path_adds_member_and_dispatches_event(): void
    {
        $command = $this->validCommand(memberCode: null);

        $newMemberId = ($this->handler)($command);

        self::assertSame(self::NEW_MEMBER_ID, $newMemberId->value);
        $stored = $this->households->findById(HouseholdId::fromString(self::HOUSEHOLD_ID));
        self::assertCount(2, $stored->members());

        $messages = $this->eventBus->dispatchedMessages();
        self::assertCount(1, $messages);
        self::assertInstanceOf(MemberAddedToHousehold::class, $messages[0]);
        self::assertSame(self::NEW_MEMBER_ID, $messages[0]->memberId->value);
        self::assertFalse($messages[0]->isPrimary);
    }

    #[Test]
    #[TestDox('Throws DuplicateMemberCode when the supplied memberCode is already used inside the household.')]
    public function rejects_duplicate_member_code(): void
    {
        $command = $this->validCommand(memberCode: self::EXISTING_CODE);

        $this->expectException(DuplicateMemberCode::class);

        ($this->handler)($command);
    }

    private function validCommand(?string $memberCode): AddMemberToHousehold
    {
        return new AddMemberToHousehold(
            householdId: self::HOUSEHOLD_ID,
            firstName: 'Bob',
            lastName: 'Smith',
            middleName: null,
            suffix: null,
            dobIso: '1992-03-04',
            genderCode: 'M',
            email: 'bob@example.com',
            phone: '5550101',
            residencyStatusCode: 'RESIDENT',
            memberCode: $memberCode,
            isPrimary: false,
        );
    }

    private function seedHousehold(): Household
    {
        return Household::register(
            HouseholdId::fromString(self::HOUSEHOLD_ID),
            HouseholdName::of('Smith Family'),
            Address::of('100 Main St', null, 'Seattle', 'WA', '98101', 'US'),
            MemberId::fromString(self::PRIMARY_ID),
            MemberCode::of(self::EXISTING_CODE),
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
