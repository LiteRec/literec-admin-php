<?php

declare(strict_types=1);

namespace App\Tests\Unit\Households\Domain;

use App\Households\Domain\Event\HouseholdAddressUpdated;
use App\Households\Domain\Event\HouseholdRegistered;
use App\Households\Domain\Event\MemberAddedToHousehold;
use App\Households\Domain\Event\MemberContactUpdated;
use App\Households\Domain\Event\MemberDeactivated;
use App\Households\Domain\Event\MemberProfileUpdated;
use App\Households\Domain\Event\MemberReactivated;
use App\Households\Domain\Event\MemberRemovedFromHousehold;
use App\Households\Domain\Event\MemberResidencyChanged;
use App\Households\Domain\Exception\DuplicateMemberCode;
use App\Households\Domain\Exception\MemberNotFound;
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
use App\Households\Domain\ValueObject\PhoneNumber;
use App\Households\Domain\ValueObject\ResidencyStatus;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[Small]
final class HouseholdTest extends TestCase
{
    private const string HOUSEHOLD_ID = '019571bf-5d51-7000-b500-000000000001';
    private const string PRIMARY_MEMBER_ID = '019571bf-5d51-7000-b500-000000000002';
    private const string SECOND_MEMBER_ID = '019571bf-5d51-7000-b500-000000000003';

    private MockClock $clock;

    protected function setUp(): void
    {
        $this->clock = new MockClock(new DateTimeImmutable('2026-01-01 12:00:00'));
    }

    #[Test]
    #[TestDox('::register() records HouseholdRegistered followed by MemberAddedToHousehold for the primary member.')]
    public function register_records_household_registered_and_primary_member_added(): void
    {
        $household = $this->register();

        $events = $household->releaseEvents();

        self::assertCount(2, $events);

        self::assertInstanceOf(HouseholdRegistered::class, $events[0]);
        self::assertSame(self::HOUSEHOLD_ID, $events[0]->householdId->value);
        self::assertSame('Smith Family', $events[0]->name->value);
        self::assertEquals($this->clock->now(), $events[0]->occurredAt);

        self::assertInstanceOf(MemberAddedToHousehold::class, $events[1]);
        self::assertSame(self::PRIMARY_MEMBER_ID, $events[1]->memberId->value);
        self::assertTrue($events[1]->isPrimary);
        self::assertSame('M0001', $events[1]->memberCode->value);
    }

    #[Test]
    #[TestDox('::addMember() records MemberAddedToHousehold for the new member.')]
    public function add_member_records_event(): void
    {
        $household = $this->register();
        $household->releaseEvents();

        $household->addMember(
            MemberId::fromString(self::SECOND_MEMBER_ID),
            MemberCode::of('M0002'),
            PersonName::of('Bob', 'Smith'),
            DateOfBirth::of(new DateTimeImmutable('1992-03-04'), $this->clock),
            Gender::Male,
            EmailAddress::of('bob@example.com'),
            PhoneNumber::of('5550002'),
            ResidencyStatus::Resident,
            false,
            $this->clock,
        );

        $events = $household->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(MemberAddedToHousehold::class, $events[0]);
        self::assertSame(self::SECOND_MEMBER_ID, $events[0]->memberId->value);
        self::assertFalse($events[0]->isPrimary);
    }

    #[Test]
    #[TestDox('::addMember() throws DuplicateMemberCode when the same code is added twice within a household.')]
    public function add_member_rejects_duplicate_code(): void
    {
        $household = $this->register();

        $this->expectException(DuplicateMemberCode::class);

        $household->addMember(
            MemberId::fromString(self::SECOND_MEMBER_ID),
            MemberCode::of('M0001'),
            PersonName::of('Bob', 'Smith'),
            DateOfBirth::of(new DateTimeImmutable('1992-03-04'), $this->clock),
            Gender::Male,
            null,
            null,
            ResidencyStatus::Resident,
            false,
            $this->clock,
        );
    }

    #[Test]
    #[TestDox('::updateMemberProfile() is a no-op when nothing changed (no event recorded).')]
    public function update_member_profile_is_noop_when_unchanged(): void
    {
        $household = $this->register();
        $household->releaseEvents();

        $household->updateMemberProfile(
            MemberId::fromString(self::PRIMARY_MEMBER_ID),
            PersonName::of('Alice', 'Smith'),
            DateOfBirth::of(new DateTimeImmutable('1990-01-01'), $this->clock),
            Gender::Female,
            $this->clock,
        );

        self::assertSame([], $household->releaseEvents());
    }

    #[Test]
    #[TestDox('::updateMemberProfile() records MemberProfileUpdated on a real change.')]
    public function update_member_profile_records_event_on_change(): void
    {
        $household = $this->register();
        $household->releaseEvents();

        $household->updateMemberProfile(
            MemberId::fromString(self::PRIMARY_MEMBER_ID),
            PersonName::of('Alice', 'Johnson'),
            DateOfBirth::of(new DateTimeImmutable('1990-01-01'), $this->clock),
            Gender::Female,
            $this->clock,
        );

        $events = $household->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(MemberProfileUpdated::class, $events[0]);
        self::assertSame(self::PRIMARY_MEMBER_ID, $events[0]->memberId->value);
    }

    #[Test]
    #[TestDox('::deactivateMember() records MemberDeactivated and is idempotent on a second call.')]
    public function deactivate_member_is_idempotent(): void
    {
        $household = $this->register();
        $household->releaseEvents();

        $household->deactivateMember(
            MemberId::fromString(self::PRIMARY_MEMBER_ID),
            'moved away',
            $this->clock,
        );

        $events = $household->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(MemberDeactivated::class, $events[0]);
        self::assertSame('moved away', $events[0]->reason);

        $household->deactivateMember(
            MemberId::fromString(self::PRIMARY_MEMBER_ID),
            'second reason',
            $this->clock,
        );

        self::assertSame([], $household->releaseEvents());
    }

    #[Test]
    #[TestDox('::reactivateMember() records MemberReactivated only when previously deactivated.')]
    public function reactivate_member_records_event_only_when_deactivated(): void
    {
        $household = $this->register();
        $household->releaseEvents();

        // No-op when already active.
        $household->reactivateMember(MemberId::fromString(self::PRIMARY_MEMBER_ID), $this->clock);
        self::assertSame([], $household->releaseEvents());

        // After deactivation, reactivate records the event.
        $household->deactivateMember(
            MemberId::fromString(self::PRIMARY_MEMBER_ID),
            'pause',
            $this->clock,
        );
        $household->releaseEvents();

        $household->reactivateMember(MemberId::fromString(self::PRIMARY_MEMBER_ID), $this->clock);

        $events = $household->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(MemberReactivated::class, $events[0]);
    }

    #[Test]
    #[TestDox('::updateAddress() records HouseholdAddressUpdated only on a real change.')]
    public function update_address_records_event_only_on_change(): void
    {
        $household = $this->register();
        $household->releaseEvents();

        // No-op when identical.
        $household->updateAddress(
            Address::of('123 Main St', 'Apt 4B', 'Springfield', 'IL', '62701', 'US'),
            $this->clock,
        );
        self::assertSame([], $household->releaseEvents());

        // Change records the event.
        $household->updateAddress(
            Address::of('456 Oak Ave', null, 'Springfield', 'IL', '62702', 'US'),
            $this->clock,
        );

        $events = $household->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(HouseholdAddressUpdated::class, $events[0]);
        self::assertSame('456 Oak Ave', $events[0]->newAddress->street);
    }

    #[Test]
    #[TestDox('::setResidencyStatus() records MemberResidencyChanged with the effective date.')]
    public function set_residency_status_records_event(): void
    {
        $household = $this->register();
        $household->releaseEvents();

        $effectiveFrom = new DateTimeImmutable('2026-02-01');

        $household->setResidencyStatus(
            MemberId::fromString(self::PRIMARY_MEMBER_ID),
            ResidencyStatus::Staff,
            $effectiveFrom,
            $this->clock,
        );

        $events = $household->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(MemberResidencyChanged::class, $events[0]);
        self::assertSame(ResidencyStatus::Staff, $events[0]->status);
        self::assertEquals($effectiveFrom, $events[0]->effectiveFrom);
    }

    #[Test]
    #[TestDox('::removeMember() records MemberRemovedFromHousehold for a known member.')]
    public function remove_member_records_event(): void
    {
        $household = $this->register();
        $household->releaseEvents();

        $secondMemberId = MemberId::fromString('019571bf-5d51-7000-b500-fedcba987654');
        $household->addMember(
            $secondMemberId,
            MemberCode::of('M0002'),
            PersonName::of('Bob', 'Smith'),
            DateOfBirth::of(new DateTimeImmutable('1992-03-04'), $this->clock),
            Gender::Male,
            null,
            null,
            ResidencyStatus::Resident,
            false,
            $this->clock,
        );
        $household->releaseEvents();

        $household->removeMember($secondMemberId, $this->clock);

        $events = $household->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(MemberRemovedFromHousehold::class, $events[0]);
        self::assertTrue($events[0]->memberId->equals($secondMemberId));
    }

    #[Test]
    #[TestDox('::removeMember() throws MemberNotFound for an unknown member.')]
    public function remove_member_throws_when_unknown(): void
    {
        $household = $this->register();

        $this->expectException(MemberNotFound::class);

        $household->removeMember(
            MemberId::fromString('019571bf-5d51-7000-b500-aaaaaaaaaaaa'),
            $this->clock,
        );
    }

    #[Test]
    #[TestDox('::updateMemberContact() records MemberContactUpdated on a real change.')]
    public function update_contact_records_event_on_change(): void
    {
        $household = $this->register();
        $household->releaseEvents();

        $household->updateMemberContact(
            MemberId::fromString(self::PRIMARY_MEMBER_ID),
            EmailAddress::of('alice.new@example.com'),
            PhoneNumber::of('5559999'),
            $this->clock,
        );

        $events = $household->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(MemberContactUpdated::class, $events[0]);
    }

    #[Test]
    #[TestDox('::updateMemberContact() is a no-op when neither value changed.')]
    public function update_contact_is_noop_when_unchanged(): void
    {
        $household = $this->register();
        $household->releaseEvents();

        $household->updateMemberContact(
            MemberId::fromString(self::PRIMARY_MEMBER_ID),
            EmailAddress::of('alice@example.com'),
            PhoneNumber::of('5550001'),
            $this->clock,
        );

        self::assertSame([], $household->releaseEvents());
    }

    #[Test]
    #[TestDox('::updateMemberProfile() throws MemberNotFound for an unknown member.')]
    public function update_profile_throws_when_member_unknown(): void
    {
        $household = $this->register();

        $this->expectException(MemberNotFound::class);

        $household->updateMemberProfile(
            MemberId::fromString('019571bf-5d51-7000-b500-bbbbbbbbbbbb'),
            PersonName::of('Ghost', 'User'),
            DateOfBirth::of(new DateTimeImmutable('1990-01-01'), $this->clock),
            Gender::Other,
            $this->clock,
        );
    }

    #[Test]
    #[TestDox('releaseEvents() returns the buffer and clears it.')]
    public function release_events_clears_buffer(): void
    {
        $household = $this->register();

        self::assertCount(2, $household->releaseEvents());
        self::assertSame([], $household->releaseEvents());
    }

    private function register(): Household
    {
        return Household::register(
            HouseholdId::fromString(self::HOUSEHOLD_ID),
            HouseholdName::of('Smith Family'),
            Address::of('123 Main St', 'Apt 4B', 'Springfield', 'IL', '62701', 'US'),
            MemberId::fromString(self::PRIMARY_MEMBER_ID),
            MemberCode::of('M0001'),
            PersonName::of('Alice', 'Smith'),
            DateOfBirth::of(new DateTimeImmutable('1990-01-01'), $this->clock),
            Gender::Female,
            EmailAddress::of('alice@example.com'),
            PhoneNumber::of('5550001'),
            ResidencyStatus::Resident,
            $this->clock,
        );
    }
}
