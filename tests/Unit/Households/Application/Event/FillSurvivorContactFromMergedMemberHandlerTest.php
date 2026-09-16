<?php

declare(strict_types=1);

namespace App\Tests\Unit\Households\Application\Event;

use App\Households\Application\Event\FillSurvivorContactFromMergedMemberHandler;
use App\Households\Domain\Event\MemberContactUpdated;
use App\Households\Domain\Event\MemberMergedInto;
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
final class FillSurvivorContactFromMergedMemberHandlerTest extends TestCase
{
    private const string SURVIVOR_HOUSEHOLD_ID = '019571bf-5d54-7000-b500-000000000f01';
    private const string SURVIVOR_ID           = '019571bf-5d54-7000-b500-000000000f02';
    private const string DUPLICATE_HOUSEHOLD_ID = '019571bf-5d54-7000-b500-000000000f03';
    private const string DUPLICATE_ID          = '019571bf-5d54-7000-b500-000000000f04';

    private MockClock $clock;
    private InMemoryHouseholds $households;
    private RecordingMessageBus $eventBus;
    private FillSurvivorContactFromMergedMemberHandler $handler;

    protected function setUp(): void
    {
        $this->clock = new MockClock(new DateTimeImmutable('2026-05-24 12:00:00'));
        $this->households = new InMemoryHouseholds();
        $this->eventBus = new RecordingMessageBus();
        $this->handler = new FillSurvivorContactFromMergedMemberHandler(
            $this->households,
            $this->clock,
            $this->eventBus,
        );
    }

    #[Test]
    #[TestDox('Fills the survivor\'s blank email/phone from the event and dispatches MemberContactUpdated.')]
    public function fills_blank_contact_and_dispatches_event(): void
    {
        $this->seedSurvivorHousehold(null, null);

        ($this->handler)(new MemberMergedInto(
            HouseholdId::fromString(self::DUPLICATE_HOUSEHOLD_ID),
            MemberId::fromString(self::DUPLICATE_ID),
            HouseholdId::fromString(self::SURVIVOR_HOUSEHOLD_ID),
            MemberId::fromString(self::SURVIVOR_ID),
            EmailAddress::of('found@example.com'),
            PhoneNumber::of('5559999'),
            $this->clock->now(),
        ));

        $reloaded = $this->households->findById(HouseholdId::fromString(self::SURVIVOR_HOUSEHOLD_ID));
        $survivorContact = $reloaded->members()[0]->contact();
        self::assertNotNull($survivorContact->email);
        self::assertTrue($survivorContact->email->equals(EmailAddress::of('found@example.com')));
        self::assertNotNull($survivorContact->phone);
        self::assertTrue($survivorContact->phone->equals(PhoneNumber::of('5559999')));

        $messages = $this->eventBus->dispatchedMessages();
        self::assertCount(1, $messages);
        self::assertInstanceOf(MemberContactUpdated::class, $messages[0]);
    }

    #[Test]
    #[TestDox('Does not dispatch when the survivor already has both fields populated.')]
    public function no_op_when_survivor_already_has_contact(): void
    {
        $this->seedSurvivorHousehold(
            EmailAddress::of('existing@example.com'),
            PhoneNumber::of('5551111'),
        );

        ($this->handler)(new MemberMergedInto(
            HouseholdId::fromString(self::DUPLICATE_HOUSEHOLD_ID),
            MemberId::fromString(self::DUPLICATE_ID),
            HouseholdId::fromString(self::SURVIVOR_HOUSEHOLD_ID),
            MemberId::fromString(self::SURVIVOR_ID),
            EmailAddress::of('found@example.com'),
            PhoneNumber::of('5559999'),
            $this->clock->now(),
        ));

        self::assertSame([], $this->eventBus->dispatchedMessages());
        $reloaded = $this->households->findById(HouseholdId::fromString(self::SURVIVOR_HOUSEHOLD_ID));
        $survivorContact = $reloaded->members()[0]->contact();
        self::assertNotNull($survivorContact->email);
        self::assertTrue($survivorContact->email->equals(EmailAddress::of('existing@example.com')));
        self::assertNotNull($survivorContact->phone);
        self::assertTrue($survivorContact->phone->equals(PhoneNumber::of('5551111')));
    }

    private function seedSurvivorHousehold(?EmailAddress $email, ?PhoneNumber $phone): void
    {
        $household = Household::register(
            HouseholdId::fromString(self::SURVIVOR_HOUSEHOLD_ID),
            HouseholdName::of('Survivor Family'),
            Address::of('100 Main St', null, 'Seattle', 'WA', '98101', 'US'),
            MemberId::fromString(self::SURVIVOR_ID),
            MemberCode::of('M000F01'),
            MemberProfile::of(
                PersonName::of('Sam', 'Survivor'),
                DateOfBirth::of(new DateTimeImmutable('1990-01-01'), $this->clock),
                Gender::Male,
            ),
            MemberContact::of($email, $phone),
            ResidencyStatus::Resident,
            $this->clock,
        );
        $household->releaseEvents();
        $this->households->save($household);
    }
}
