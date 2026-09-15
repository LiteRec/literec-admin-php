<?php

declare(strict_types=1);

namespace App\Tests\Unit\Households\Domain;

use App\Households\Domain\Event\HouseholdAddressUpdated;
use App\Households\Domain\Event\HouseholdRegistered;
use App\Households\Domain\Event\MemberAddedToHousehold;
use App\Households\Domain\Event\MemberAnonymized;
use App\Households\Domain\Event\MemberContactUpdated;
use App\Households\Domain\Event\MemberDeactivated;
use App\Households\Domain\Event\MemberMergedInto;
use App\Households\Domain\Event\MemberPhotoAttached;
use App\Households\Domain\Event\MemberPhotoReleased;
use App\Households\Domain\Event\MemberPhotoRemoved;
use App\Households\Domain\Event\MemberProfileUpdated;
use App\Households\Domain\Event\MemberReactivated;
use App\Households\Domain\Event\MemberRemovedFromHousehold;
use App\Households\Domain\Event\MemberResidencyChanged;
use App\Households\Domain\Event\MemberSharedWithHousehold;
use App\Households\Domain\Event\MemberSharingWithdrawn;
use App\Households\Domain\Event\MemberSplitOff;
use App\Households\Domain\Exception\CannotMergeMemberIntoItself;
use App\Households\Domain\Exception\CannotShareWithHomeHousehold;
use App\Households\Domain\Exception\DuplicateMemberCode;
use App\Households\Domain\Exception\DuplicateMemberId;
use App\Households\Domain\Exception\HouseholdAlreadyLinked;
use App\Households\Domain\Exception\InvariantViolation;
use App\Households\Domain\Exception\MemberAlreadyMerged;
use App\Households\Domain\Exception\MemberIsAnonymized;
use App\Households\Domain\Exception\MemberNotAMinor;
use App\Households\Domain\Exception\MemberNotFound;
use App\Households\Domain\Exception\SplitSelectionEmpty;
use App\Households\Domain\Household;
use App\Households\Domain\ValueObject\Address;
use App\Households\Domain\ValueObject\AnonymizedProfile;
use App\Households\Domain\ValueObject\DateOfBirth;
use App\Shared\Domain\ValueObject\EmailAddress;
use App\Households\Domain\ValueObject\Gender;
use App\Households\Domain\ValueObject\Height;
use App\Households\Domain\ValueObject\HouseholdId;
use App\Households\Domain\ValueObject\HouseholdName;
use App\Households\Domain\ValueObject\ImageFormat;
use App\Households\Domain\ValueObject\MemberCode;
use App\Households\Domain\ValueObject\MemberId;
use App\Households\Domain\ValueObject\PersonName;
use App\Households\Domain\ValueObject\ProfilePhoto;
use App\Shared\Domain\ValueObject\PhoneNumber;
use App\Households\Domain\ValueObject\ResidencyStatus;
use App\Households\Domain\ValueObject\Salutation;
use App\Households\Domain\ValueObject\TransactionReferences;
use App\Households\Domain\ValueObject\Weight;
use DateTimeImmutable;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
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
    private const string MINOR_MEMBER_ID = '019571bf-5d51-7000-b500-000000000004';
    private const string TARGET_HOUSEHOLD_ID = '019571bf-5d51-7000-b500-000000000005';

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
    #[TestDox('::addMember() throws DuplicateMemberId when the same id is added twice within a household.')]
    public function add_member_rejects_duplicate_id(): void
    {
        $household = $this->register();

        $this->expectException(DuplicateMemberId::class);

        $household->addMember(
            MemberId::fromString(self::PRIMARY_MEMBER_ID),
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
    #[TestDox('::updateMemberProfile() records MemberProfileUpdated on a salutation/height/weight-only change.')]
    public function update_member_profile_records_event_on_measurement_only_change(): void
    {
        $household = $this->register();
        $household->releaseEvents();

        $household->updateMemberProfile(
            MemberId::fromString(self::PRIMARY_MEMBER_ID),
            PersonName::of('Alice', 'Smith'),
            DateOfBirth::of(new DateTimeImmutable('1990-01-01'), $this->clock),
            Gender::Female,
            $this->clock,
            Salutation::Ms,
            Height::ofInches(65),
            Weight::ofPounds(140),
        );

        $events = $household->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(MemberProfileUpdated::class, $events[0]);
    }

    #[Test]
    #[TestDox('::updateMemberProfile() records MemberProfileUpdated when only the nickname changes.')]
    public function update_member_profile_records_event_on_nickname_only_change(): void
    {
        $household = $this->register();
        $household->releaseEvents();

        $household->updateMemberProfile(
            MemberId::fromString(self::PRIMARY_MEMBER_ID),
            PersonName::of('Alice', 'Smith', nickname: 'Al'),
            DateOfBirth::of(new DateTimeImmutable('1990-01-01'), $this->clock),
            Gender::Female,
            $this->clock,
        );

        $events = $household->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(MemberProfileUpdated::class, $events[0]);
    }

    #[Test]
    #[TestDox('::updateMemberProfile() is a no-op when salutation, height, and weight stay null-to-null.')]
    public function update_member_profile_is_noop_when_measurements_stay_null(): void
    {
        $household = $this->register();
        $household->releaseEvents();

        $household->updateMemberProfile(
            MemberId::fromString(self::PRIMARY_MEMBER_ID),
            PersonName::of('Alice', 'Smith'),
            DateOfBirth::of(new DateTimeImmutable('1990-01-01'), $this->clock),
            Gender::Female,
            $this->clock,
            null,
            null,
            null,
        );

        self::assertSame([], $household->releaseEvents());
    }

    #[Test]
    #[TestDox('::updateMemberProfile() is a no-op when salutation, height, and weight are resubmitted unchanged.')]
    public function update_member_profile_is_noop_when_measurements_unchanged(): void
    {
        $household = $this->register();
        $household->updateMemberProfile(
            MemberId::fromString(self::PRIMARY_MEMBER_ID),
            PersonName::of('Alice', 'Smith'),
            DateOfBirth::of(new DateTimeImmutable('1990-01-01'), $this->clock),
            Gender::Female,
            $this->clock,
            Salutation::Ms,
            Height::ofInches(65),
            Weight::ofPounds(140),
        );
        $household->releaseEvents();

        $household->updateMemberProfile(
            MemberId::fromString(self::PRIMARY_MEMBER_ID),
            PersonName::of('Alice', 'Smith'),
            DateOfBirth::of(new DateTimeImmutable('1990-01-01'), $this->clock),
            Gender::Female,
            $this->clock,
            Salutation::Ms,
            Height::ofInches(65),
            Weight::ofPounds(140),
        );

        self::assertSame([], $household->releaseEvents());
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
    #[TestDox('::anonymizeMember() replaces PII with placeholder values and keeps the member\'s id and code.')]
    public function anonymize_member_replaces_pii_with_placeholders_and_keeps_id_and_code(): void
    {
        $household = $this->registerWithSalutationHeightAndWeight();
        $household->releaseEvents();
        $memberId = MemberId::fromString(self::PRIMARY_MEMBER_ID);
        $originalCode = $this->memberById($household, $memberId)->code();

        $household->anonymizeMember($memberId, AnonymizedProfile::placeholder(), $this->clock);

        $member = $this->memberById($household, $memberId);
        self::assertTrue($member->id()->equals($memberId));
        self::assertTrue($member->code()->equals($originalCode));
        self::assertSame('Anonymized', $member->name()->firstName);
        self::assertSame('Member', $member->name()->lastName);
        self::assertSame('1900-01-01', $member->dateOfBirth()->value->format('Y-m-d'));
        self::assertSame(Gender::Unspecified, $member->gender());
        self::assertNull($member->email());
        self::assertNull($member->phone());
        self::assertNull($member->salutation());
        self::assertNull($member->height());
        self::assertNull($member->weight());
    }

    #[Test]
    #[TestDox('::anonymizeMember() releases an attached photo, recording MemberPhotoReleased for its storage key.')]
    public function anonymize_member_releases_attached_photo(): void
    {
        $household = $this->register();
        $memberId = MemberId::fromString(self::PRIMARY_MEMBER_ID);
        $photo = $this->photo('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa.jpg');
        $household->attachMemberPhoto($memberId, $photo, $this->clock);
        $household->releaseEvents();

        $household->anonymizeMember($memberId, AnonymizedProfile::placeholder(), $this->clock);

        $events = $household->releaseEvents();
        self::assertInstanceOf(MemberAnonymized::class, $events[0]);
        self::assertInstanceOf(MemberPhotoReleased::class, $events[1]);
        self::assertSame($photo->storageKey, $events[1]->storageKey);
        self::assertNull($this->memberById($household, $memberId)->photo());
    }

    #[Test]
    #[TestDox('::anonymizeMember() records MemberAnonymized carrying only ids and a timestamp, never PII.')]
    public function anonymize_member_records_member_anonymized_without_pii(): void
    {
        $household = $this->register();
        $household->releaseEvents();
        $memberId = MemberId::fromString(self::PRIMARY_MEMBER_ID);

        $household->anonymizeMember($memberId, AnonymizedProfile::placeholder(), $this->clock);

        $events = $household->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(MemberAnonymized::class, $events[0]);
        self::assertTrue($events[0]->householdId->equals(HouseholdId::fromString(self::HOUSEHOLD_ID)));
        self::assertTrue($events[0]->memberId->equals($memberId));
        self::assertEquals($this->clock->now(), $events[0]->occurredAt);
        // Exactly the three declared public properties exist on the event —
        // no name, email, phone, or other free text could have been
        // smuggled in.
        self::assertSame(['householdId', 'memberId', 'occurredAt'], array_keys(get_object_vars($events[0])));
    }

    #[Test]
    #[TestDox('::anonymizeMember() throws MemberIsAnonymized when called a second time.')]
    public function anonymize_member_throws_when_already_anonymized(): void
    {
        $household = $this->register();
        $memberId = MemberId::fromString(self::PRIMARY_MEMBER_ID);
        $household->anonymizeMember($memberId, AnonymizedProfile::placeholder(), $this->clock);
        $household->releaseEvents();

        $this->expectException(MemberIsAnonymized::class);

        $household->anonymizeMember($memberId, AnonymizedProfile::placeholder(), $this->clock);
    }

    #[Test]
    #[TestDox('::anonymizeMember() also deactivates the member, recording the fixed "Anonymized" reason.')]
    public function anonymize_member_deactivates_the_member_with_fixed_reason(): void
    {
        $household = $this->register();
        $memberId = MemberId::fromString(self::PRIMARY_MEMBER_ID);

        $household->anonymizeMember($memberId, AnonymizedProfile::placeholder(), $this->clock);

        $member = $this->memberById($household, $memberId);
        self::assertFalse($member->isActive());
        self::assertSame('Anonymized', $member->deactivation()?->reason);
        self::assertTrue($member->isAnonymized());
        self::assertEquals($this->clock->now(), $member->anonymizedAt());
    }

    #[Test]
    #[TestDox('::anonymizeMember() scrubs the household name and address when the member was the last one remaining.')]
    public function anonymize_member_scrubs_household_name_and_address_when_last_member(): void
    {
        $household = $this->register();
        $memberId = MemberId::fromString(self::PRIMARY_MEMBER_ID);

        $household->anonymizeMember($memberId, AnonymizedProfile::placeholder(), $this->clock);

        self::assertSame('Anonymized Household', $household->name()->value);
        self::assertSame('ZZ', $household->address()->country);
    }

    #[Test]
    #[TestDox('::anonymizeMember() keeps the household name and address when other non-anonymized members remain.')]
    public function anonymize_member_keeps_household_name_when_other_members_remain(): void
    {
        $household = $this->register();
        $household->addMember(
            MemberId::fromString(self::SECOND_MEMBER_ID),
            MemberCode::of('M0002'),
            PersonName::of('Bob', 'Smith'),
            DateOfBirth::of(new DateTimeImmutable('1992-01-01'), $this->clock),
            Gender::Male,
            null,
            null,
            ResidencyStatus::Resident,
            false,
            $this->clock,
        );

        $household->anonymizeMember(
            MemberId::fromString(self::PRIMARY_MEMBER_ID),
            AnonymizedProfile::placeholder(),
            $this->clock,
        );

        self::assertSame('Smith Family', $household->name()->value);
        self::assertSame('US', $household->address()->country);
    }

    #[Test]
    #[TestDox('::anonymizeMember() scrubs the household when the only other member is merged, not anonymized.')]
    public function anonymize_member_scrubs_household_when_remaining_member_is_merged(): void
    {
        $household = $this->register();
        $household->addMember(
            MemberId::fromString(self::SECOND_MEMBER_ID),
            MemberCode::of('M0002'),
            PersonName::of('Bob', 'Smith'),
            DateOfBirth::of(new DateTimeImmutable('1992-01-01'), $this->clock),
            Gender::Male,
            null,
            null,
            ResidencyStatus::Resident,
            false,
            $this->clock,
        );
        $household->mergeMemberInto(
            MemberId::fromString(self::SECOND_MEMBER_ID),
            HouseholdId::fromString('019571bf-5d51-7000-b500-000000000099'),
            MemberId::fromString('019571bf-5d51-7000-b500-000000000098'),
            $this->clock,
        );

        $household->anonymizeMember(
            MemberId::fromString(self::PRIMARY_MEMBER_ID),
            AnonymizedProfile::placeholder(),
            $this->clock,
        );

        self::assertSame('Anonymized Household', $household->name()->value);
        self::assertSame('ZZ', $household->address()->country);
    }

    /**
     * @return Generator<string, array{mutate: callable(Household, MemberId, MockClock): void}>
     */
    public static function anonymizedMemberMutatorCases(): Generator
    {
        yield 'updateMemberProfile' => ['mutate' => static function (
            Household $h,
            MemberId $id,
            MockClock $clock,
        ): void {
            $h->updateMemberProfile(
                $id,
                PersonName::of('Changed', 'Name'),
                DateOfBirth::of(new DateTimeImmutable('1990-01-01'), $clock),
                Gender::Male,
                $clock,
            );
        }];
        yield 'updateMemberContact' => ['mutate' => static function (
            Household $h,
            MemberId $id,
            MockClock $clock,
        ): void {
            $h->updateMemberContact($id, EmailAddress::of('new@example.com'), null, $clock);
        }];
        yield 'reactivateMember' => ['mutate' => static function (Household $h, MemberId $id, MockClock $clock): void {
            $h->reactivateMember($id, $clock);
        }];
        yield 'setResidencyStatus' => ['mutate' => static function (
            Household $h,
            MemberId $id,
            MockClock $clock,
        ): void {
            $h->setResidencyStatus($id, ResidencyStatus::NonResident, $clock->now(), $clock);
        }];
    }

    #[Test]
    #[DataProvider('anonymizedMemberMutatorCases')]
    #[TestDox('every other mutator throws MemberIsAnonymized when the target member is anonymized.')]
    public function mutators_throw_member_is_anonymized_on_anonymized_member(callable $mutate): void
    {
        $household = $this->register();
        $memberId = MemberId::fromString(self::PRIMARY_MEMBER_ID);
        $household->anonymizeMember($memberId, AnonymizedProfile::placeholder(), $this->clock);
        $household->releaseEvents();

        $this->expectException(MemberIsAnonymized::class);

        $mutate($household, $memberId, $this->clock);
    }

    #[Test]
    #[TestDox('::mergeMemberInto() records MemberMergedInto carrying the duplicate\'s contact and marks it merged.')]
    public function merge_member_into_records_event_with_contact(): void
    {
        $household = $this->register();
        $household->releaseEvents();
        $duplicateId = MemberId::fromString(self::PRIMARY_MEMBER_ID);
        $survivorHouseholdId = HouseholdId::fromString('019571bf-5d51-7000-b500-000000000099');
        $survivorId = MemberId::fromString('019571bf-5d51-7000-b500-000000000098');

        $household->mergeMemberInto($duplicateId, $survivorHouseholdId, $survivorId, $this->clock);

        $events = $household->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(MemberMergedInto::class, $events[0]);
        self::assertTrue($events[0]->householdId->equals(HouseholdId::fromString(self::HOUSEHOLD_ID)));
        self::assertTrue($events[0]->memberId->equals($duplicateId));
        self::assertTrue($events[0]->survivorHouseholdId->equals($survivorHouseholdId));
        self::assertTrue($events[0]->survivorMemberId->equals($survivorId));
        self::assertNotNull($events[0]->email);
        self::assertTrue($events[0]->email->equals(EmailAddress::of('alice@example.com')));
        self::assertNotNull($events[0]->phone);
        self::assertTrue($events[0]->phone->equals(PhoneNumber::of('5550001')));
        self::assertEquals($this->clock->now(), $events[0]->occurredAt);

        $merged = $this->memberById($household, $duplicateId);
        self::assertTrue($merged->isMerged());
        $merge = $merged->merge();
        self::assertNotNull($merge);
        self::assertTrue($merge->intoMemberId->equals($survivorId));
    }

    #[Test]
    #[TestDox('::mergeMemberInto() throws CannotMergeMemberIntoItself when the duplicate and survivor ids match.')]
    public function merge_member_into_rejects_self_merge(): void
    {
        $household = $this->register();
        $memberId = MemberId::fromString(self::PRIMARY_MEMBER_ID);

        $this->expectException(CannotMergeMemberIntoItself::class);

        $household->mergeMemberInto($memberId, HouseholdId::fromString(self::HOUSEHOLD_ID), $memberId, $this->clock);
    }

    #[Test]
    #[TestDox('::mergeMemberInto() throws MemberAlreadyMerged when the duplicate is already merged.')]
    public function merge_member_into_rejects_already_merged_duplicate(): void
    {
        $household = $this->register();
        $duplicateId = MemberId::fromString(self::PRIMARY_MEMBER_ID);
        $survivorId = MemberId::fromString('019571bf-5d51-7000-b500-000000000098');
        $household->mergeMemberInto(
            $duplicateId,
            HouseholdId::fromString('019571bf-5d51-7000-b500-000000000099'),
            $survivorId,
            $this->clock,
        );
        $household->releaseEvents();

        $this->expectException(MemberAlreadyMerged::class);

        $household->mergeMemberInto(
            $duplicateId,
            HouseholdId::fromString('019571bf-5d51-7000-b500-000000000099'),
            $survivorId,
            $this->clock,
        );
    }

    /**
     * @return Generator<string, array{mutate: callable(Household, MemberId, MockClock): void}>
     */
    public static function mergedMemberMutatorCases(): Generator
    {
        yield 'updateMemberProfile' => ['mutate' => static function (
            Household $h,
            MemberId $id,
            MockClock $clock,
        ): void {
            $h->updateMemberProfile(
                $id,
                PersonName::of('Changed', 'Name'),
                DateOfBirth::of(new DateTimeImmutable('1990-01-01'), $clock),
                Gender::Male,
                $clock,
            );
        }];
        yield 'updateMemberContact' => ['mutate' => static function (
            Household $h,
            MemberId $id,
            MockClock $clock,
        ): void {
            $h->updateMemberContact($id, EmailAddress::of('new@example.com'), null, $clock);
        }];
        yield 'setResidencyStatus' => ['mutate' => static function (
            Household $h,
            MemberId $id,
            MockClock $clock,
        ): void {
            $h->setResidencyStatus($id, ResidencyStatus::Member, $clock->now(), $clock);
        }];
        yield 'deactivateMember' => ['mutate' => static function (Household $h, MemberId $id, MockClock $clock): void {
            $h->deactivateMember($id, 'reason', $clock);
        }];
        yield 'reactivateMember' => ['mutate' => static function (Household $h, MemberId $id, MockClock $clock): void {
            $h->reactivateMember($id, $clock);
        }];
        yield 'removeMember' => ['mutate' => static function (Household $h, MemberId $id, MockClock $clock): void {
            $h->removeMember($id, $clock);
        }];
        yield 'attachMemberPhoto' => ['mutate' => static function (Household $h, MemberId $id, MockClock $clock): void {
            $photo = ProfilePhoto::of(
                $id->value . '/aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa.jpg',
                ImageFormat::Jpeg,
                $clock->now(),
            );
            $h->attachMemberPhoto($id, $photo, $clock);
        }];
        yield 'removeMemberPhoto' => ['mutate' => static function (Household $h, MemberId $id, MockClock $clock): void {
            $h->removeMemberPhoto($id, $clock);
        }];
        yield 'splitMember' => ['mutate' => static function (Household $h, MemberId $id, MockClock $clock): void {
            $h->splitMember(
                $id,
                MemberId::fromString('019571bf-5d51-7000-b500-000000000097'),
                MemberCode::of('M0099'),
                PersonName::of('New', 'Person'),
                null,
                null,
                TransactionReferences::fromStrings(['txn-1']),
                null,
                $clock,
            );
        }];
    }

    #[Test]
    #[DataProvider('mergedMemberMutatorCases')]
    #[TestDox('every other mutator throws MemberAlreadyMerged when the target member is merged.')]
    public function mutators_throw_member_already_merged_on_merged_member(callable $mutate): void
    {
        $household = $this->register();
        $memberId = MemberId::fromString(self::PRIMARY_MEMBER_ID);
        $household->mergeMemberInto(
            $memberId,
            HouseholdId::fromString('019571bf-5d51-7000-b500-000000000099'),
            MemberId::fromString('019571bf-5d51-7000-b500-000000000098'),
            $this->clock,
        );
        $household->releaseEvents();

        $this->expectException(MemberAlreadyMerged::class);

        $mutate($household, $memberId, $this->clock);
    }

    #[Test]
    #[TestDox('::splitMember() records MemberAddedToHousehold then MemberSplitOff, copying DOB/gender/residency.')]
    public function split_member_records_added_then_split_off(): void
    {
        $household = $this->register();
        $household->releaseEvents();
        $sourceId = MemberId::fromString(self::PRIMARY_MEMBER_ID);
        $newId = MemberId::fromString(self::SECOND_MEMBER_ID);
        $newCode = MemberCode::of('M0002');
        $transactions = TransactionReferences::fromStrings(['txn-1', 'txn-2']);

        $household->splitMember(
            $sourceId,
            $newId,
            $newCode,
            PersonName::of('Bob', 'Smith'),
            EmailAddress::of('bob@example.com'),
            PhoneNumber::of('5550002'),
            $transactions,
            'Two people share one record',
            $this->clock,
        );

        $events = $household->releaseEvents();
        self::assertCount(2, $events);

        self::assertInstanceOf(MemberAddedToHousehold::class, $events[0]);
        self::assertTrue($events[0]->memberId->equals($newId));
        self::assertFalse($events[0]->isPrimary);

        self::assertInstanceOf(MemberSplitOff::class, $events[1]);
        self::assertTrue($events[1]->householdId->equals(HouseholdId::fromString(self::HOUSEHOLD_ID)));
        self::assertTrue($events[1]->sourceMemberId->equals($sourceId));
        self::assertTrue($events[1]->newMemberId->equals($newId));
        self::assertTrue($events[1]->newMemberCode->equals($newCode));
        self::assertTrue($events[1]->transactions->equals($transactions));
        self::assertSame('Two people share one record', $events[1]->reason);
        self::assertEquals($this->clock->now(), $events[1]->occurredAt);

        $source = $this->memberById($household, $sourceId);
        $new = $this->memberById($household, $newId);
        self::assertTrue($new->dateOfBirth()->equals($source->dateOfBirth()));
        self::assertSame($source->gender(), $new->gender());
        self::assertSame($source->residencyStatus(), $new->residencyStatus());
        self::assertSame('Bob', $new->name()->firstName);
        self::assertNotNull($new->email());
        self::assertTrue($new->email()->equals(EmailAddress::of('bob@example.com')));
    }

    #[Test]
    #[TestDox('::splitMember() throws SplitSelectionEmpty when no transactions are selected.')]
    public function split_member_rejects_empty_selection(): void
    {
        $household = $this->register();
        $sourceId = MemberId::fromString(self::PRIMARY_MEMBER_ID);

        $this->expectException(SplitSelectionEmpty::class);

        $household->splitMember(
            $sourceId,
            MemberId::fromString(self::SECOND_MEMBER_ID),
            MemberCode::of('M0002'),
            PersonName::of('Bob', 'Smith'),
            null,
            null,
            TransactionReferences::fromStrings([]),
            null,
            $this->clock,
        );
    }

    #[Test]
    #[TestDox('::splitMember() throws MemberNotFound when the source member does not belong to this household.')]
    public function split_member_rejects_unknown_source(): void
    {
        $household = $this->register();

        $this->expectException(MemberNotFound::class);

        $household->splitMember(
            MemberId::fromString('019571bf-5d51-7000-b500-000000000096'),
            MemberId::fromString(self::SECOND_MEMBER_ID),
            MemberCode::of('M0002'),
            PersonName::of('Bob', 'Smith'),
            null,
            null,
            TransactionReferences::fromStrings(['txn-1']),
            null,
            $this->clock,
        );
    }

    #[Test]
    #[TestDox('::splitMember() is allowed on a deactivated source member.')]
    public function split_member_allows_deactivated_source(): void
    {
        $household = $this->register();
        $sourceId = MemberId::fromString(self::PRIMARY_MEMBER_ID);
        $household->deactivateMember($sourceId, 'moved away', $this->clock);
        $household->releaseEvents();

        $household->splitMember(
            $sourceId,
            MemberId::fromString(self::SECOND_MEMBER_ID),
            MemberCode::of('M0002'),
            PersonName::of('Bob', 'Smith'),
            null,
            null,
            TransactionReferences::fromStrings(['txn-1']),
            null,
            $this->clock,
        );

        $events = $household->releaseEvents();
        self::assertCount(2, $events);
        self::assertInstanceOf(MemberSplitOff::class, $events[1]);
    }

    #[Test]
    #[TestDox('::fillMemberContactGaps() fills only blank email/phone and records MemberContactUpdated.')]
    public function fill_member_contact_gaps_fills_only_blanks(): void
    {
        $household = $this->register();
        $household->addMember(
            MemberId::fromString(self::SECOND_MEMBER_ID),
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
        $secondId = MemberId::fromString(self::SECOND_MEMBER_ID);

        $household->fillMemberContactGaps(
            $secondId,
            EmailAddress::of('found@example.com'),
            PhoneNumber::of('5559999'),
            $this->clock,
        );

        $events = $household->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(MemberContactUpdated::class, $events[0]);
        $second = $this->memberById($household, $secondId);
        self::assertNotNull($second->email());
        self::assertTrue($second->email()->equals(EmailAddress::of('found@example.com')));
        self::assertNotNull($second->phone());
        self::assertTrue($second->phone()->equals(PhoneNumber::of('5559999')));
    }

    #[Test]
    #[TestDox('::fillMemberContactGaps() is silent when the survivor already has both fields populated.')]
    public function fill_member_contact_gaps_is_silent_when_nothing_blank(): void
    {
        $household = $this->register();
        $household->releaseEvents();
        $primaryId = MemberId::fromString(self::PRIMARY_MEMBER_ID);

        $household->fillMemberContactGaps(
            $primaryId,
            EmailAddress::of('duplicate@example.com'),
            PhoneNumber::of('5551234'),
            $this->clock,
        );

        self::assertSame([], $household->releaseEvents());
        $primary = $this->memberById($household, $primaryId);
        self::assertNotNull($primary->email());
        self::assertTrue($primary->email()->equals(EmailAddress::of('alice@example.com')));
        self::assertNotNull($primary->phone());
        self::assertTrue($primary->phone()->equals(PhoneNumber::of('5550001')));
    }

    #[Test]
    #[TestDox('::attachMemberPhoto() records MemberPhotoAttached and attaches the photo to the member.')]
    public function attach_member_photo_records_event_and_attaches_photo(): void
    {
        $household = $this->register();
        $household->releaseEvents();
        $memberId = MemberId::fromString(self::PRIMARY_MEMBER_ID);
        $photo = $this->photo('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa.jpg');

        $household->attachMemberPhoto($memberId, $photo, $this->clock);

        $events = $household->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(MemberPhotoAttached::class, $events[0]);
        self::assertSame($photo->storageKey, $events[0]->storageKey);

        $member = $this->memberById($household, $memberId);
        self::assertNotNull($member->photo());
        self::assertTrue($member->photo()->equals($photo));
    }

    #[Test]
    #[TestDox('::attachMemberPhoto() replacing a photo also records MemberPhotoReleased for the superseded key.')]
    public function attach_member_photo_replacing_releases_previous_key(): void
    {
        $household = $this->register();
        $memberId = MemberId::fromString(self::PRIMARY_MEMBER_ID);
        $first = $this->photo('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa.jpg');
        $household->attachMemberPhoto($memberId, $first, $this->clock);
        $household->releaseEvents();

        $second = $this->photo('bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb.png');
        $household->attachMemberPhoto($memberId, $second, $this->clock);

        $events = $household->releaseEvents();
        self::assertCount(2, $events);
        self::assertInstanceOf(MemberPhotoAttached::class, $events[0]);
        self::assertSame($second->storageKey, $events[0]->storageKey);
        self::assertInstanceOf(MemberPhotoReleased::class, $events[1]);
        self::assertSame($first->storageKey, $events[1]->storageKey);

        $member = $this->memberById($household, $memberId);
        self::assertNotNull($member->photo());
        self::assertTrue($member->photo()->equals($second));
    }

    #[Test]
    #[TestDox('::removeMemberPhoto() is a no-op when the member has no photo.')]
    public function remove_member_photo_is_no_op_without_photo(): void
    {
        $household = $this->register();
        $household->releaseEvents();

        $household->removeMemberPhoto(MemberId::fromString(self::PRIMARY_MEMBER_ID), $this->clock);

        self::assertSame([], $household->releaseEvents());
    }

    #[Test]
    #[TestDox('::removeMemberPhoto() records MemberPhotoRemoved and MemberPhotoReleased and clears the photo.')]
    public function remove_member_photo_records_events_and_clears_photo(): void
    {
        $household = $this->register();
        $memberId = MemberId::fromString(self::PRIMARY_MEMBER_ID);
        $photo = $this->photo('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa.jpg');
        $household->attachMemberPhoto($memberId, $photo, $this->clock);
        $household->releaseEvents();

        $household->removeMemberPhoto($memberId, $this->clock);

        $events = $household->releaseEvents();
        self::assertCount(2, $events);
        self::assertInstanceOf(MemberPhotoRemoved::class, $events[0]);
        self::assertInstanceOf(MemberPhotoReleased::class, $events[1]);
        self::assertSame($photo->storageKey, $events[1]->storageKey);

        self::assertNull($this->memberById($household, $memberId)->photo());
    }

    #[Test]
    #[TestDox('members() clones carry the photo alongside every other member field.')]
    public function members_clone_carries_photo(): void
    {
        $household = $this->register();
        $memberId = MemberId::fromString(self::PRIMARY_MEMBER_ID);
        $photo = $this->photo('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa.jpg');
        $household->attachMemberPhoto($memberId, $photo, $this->clock);

        $clone = $this->memberById($household, $memberId);

        self::assertNotNull($clone->photo());
        self::assertTrue($clone->photo()->equals($photo));
    }

    private function photo(string $basename): ProfilePhoto
    {
        return ProfilePhoto::of(
            self::PRIMARY_MEMBER_ID . '/' . $basename,
            ImageFormat::fromMimeType('image/' . (str_ends_with($basename, '.png') ? 'png' : 'jpeg')),
            $this->clock->now(),
        );
    }

    private function memberById(Household $household, MemberId $memberId): \App\Households\Domain\HouseholdMember
    {
        foreach ($household->members() as $member) {
            if ($member->id()->equals($memberId)) {
                return $member;
            }
        }
        self::fail('Member not found in household.');
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
    #[TestDox('::updateMemberContact() with (null, null) clears both channels and records the event with null values.')]
    public function update_contact_clears_both_channels_when_given_null(): void
    {
        $household = $this->register();
        $household->releaseEvents();

        $household->updateMemberContact(
            MemberId::fromString(self::PRIMARY_MEMBER_ID),
            null,
            null,
            $this->clock,
        );

        $events = $household->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(MemberContactUpdated::class, $events[0]);
        self::assertNull($events[0]->email);
        self::assertNull($events[0]->phone);
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

    #[Test]
    #[TestDox('::shareMemberWithHousehold() records MemberSharedWithHousehold and updates sharedHouseholdIds().')]
    public function share_member_with_household_records_event_and_updates_shared_ids(): void
    {
        $household = $this->registerWithMinorMember();
        $household->releaseEvents();

        $household->shareMemberWithHousehold(
            MemberId::fromString(self::MINOR_MEMBER_ID),
            HouseholdId::fromString(self::TARGET_HOUSEHOLD_ID),
            $this->clock,
        );

        $events = $household->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(MemberSharedWithHousehold::class, $events[0]);
        self::assertSame(self::HOUSEHOLD_ID, $events[0]->householdId->value);
        self::assertSame(self::MINOR_MEMBER_ID, $events[0]->memberId->value);
        self::assertSame(self::TARGET_HOUSEHOLD_ID, $events[0]->sharedHouseholdId->value);
        self::assertEquals($this->clock->now(), $events[0]->occurredAt);

        $minor = $this->memberById($household, MemberId::fromString(self::MINOR_MEMBER_ID));
        self::assertTrue($minor->isSharedWith(HouseholdId::fromString(self::TARGET_HOUSEHOLD_ID)));
        self::assertEquals(
            [HouseholdId::fromString(self::TARGET_HOUSEHOLD_ID)],
            $minor->sharedHouseholdIds(),
        );
        self::assertEquals(
            $this->clock->now(),
            $minor->linkedAtFor(HouseholdId::fromString(self::TARGET_HOUSEHOLD_ID)),
        );
        self::assertNull($minor->linkedAtFor(HouseholdId::fromString(self::HOUSEHOLD_ID)));
    }

    #[Test]
    #[TestDox('::shareMemberWithHousehold() throws MemberNotFound for an unknown member.')]
    public function share_member_throws_when_member_unknown(): void
    {
        $household = $this->registerWithMinorMember();

        $this->expectException(MemberNotFound::class);

        $household->shareMemberWithHousehold(
            MemberId::fromString('019571bf-5d51-7000-b500-bbbbbbbbbbbb'),
            HouseholdId::fromString(self::TARGET_HOUSEHOLD_ID),
            $this->clock,
        );
    }

    #[Test]
    #[TestDox('::shareMemberWithHousehold() throws InvariantViolation for a deactivated member.')]
    public function share_member_throws_for_inactive_member(): void
    {
        $household = $this->registerWithMinorMember();
        $household->deactivateMember(MemberId::fromString(self::MINOR_MEMBER_ID), 'moved away', $this->clock);

        $this->expectException(InvariantViolation::class);

        $household->shareMemberWithHousehold(
            MemberId::fromString(self::MINOR_MEMBER_ID),
            HouseholdId::fromString(self::TARGET_HOUSEHOLD_ID),
            $this->clock,
        );
    }

    #[Test]
    #[TestDox('::shareMemberWithHousehold() throws CannotShareWithHomeHousehold when the target is this household.')]
    public function share_member_throws_when_target_is_home_household(): void
    {
        $household = $this->registerWithMinorMember();

        $this->expectException(CannotShareWithHomeHousehold::class);

        $household->shareMemberWithHousehold(
            MemberId::fromString(self::MINOR_MEMBER_ID),
            HouseholdId::fromString(self::HOUSEHOLD_ID),
            $this->clock,
        );
    }

    #[Test]
    #[TestDox('::shareMemberWithHousehold() throws MemberNotAMinor for a member who is 18 or older.')]
    public function share_member_throws_for_adult_member(): void
    {
        $household = $this->registerWithMinorMember();

        $this->expectException(MemberNotAMinor::class);

        $household->shareMemberWithHousehold(
            MemberId::fromString(self::PRIMARY_MEMBER_ID),
            HouseholdId::fromString(self::TARGET_HOUSEHOLD_ID),
            $this->clock,
        );
    }

    #[Test]
    #[TestDox('::shareMemberWithHousehold() throws HouseholdAlreadyLinked when already shared with the target.')]
    public function share_member_throws_when_already_linked(): void
    {
        $household = $this->registerWithMinorMember();
        $household->shareMemberWithHousehold(
            MemberId::fromString(self::MINOR_MEMBER_ID),
            HouseholdId::fromString(self::TARGET_HOUSEHOLD_ID),
            $this->clock,
        );

        $this->expectException(HouseholdAlreadyLinked::class);

        $household->shareMemberWithHousehold(
            MemberId::fromString(self::MINOR_MEMBER_ID),
            HouseholdId::fromString(self::TARGET_HOUSEHOLD_ID),
            $this->clock,
        );
    }

    #[Test]
    #[TestDox('::withdrawMemberFromHousehold() records MemberSharingWithdrawn and removes the target link.')]
    public function withdraw_member_from_household_records_event_and_removes_link(): void
    {
        $household = $this->registerWithMinorMember();
        $household->shareMemberWithHousehold(
            MemberId::fromString(self::MINOR_MEMBER_ID),
            HouseholdId::fromString(self::TARGET_HOUSEHOLD_ID),
            $this->clock,
        );
        $household->releaseEvents();

        $household->withdrawMemberFromHousehold(
            MemberId::fromString(self::MINOR_MEMBER_ID),
            HouseholdId::fromString(self::TARGET_HOUSEHOLD_ID),
            $this->clock,
        );

        $events = $household->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(MemberSharingWithdrawn::class, $events[0]);
        self::assertSame(self::TARGET_HOUSEHOLD_ID, $events[0]->sharedHouseholdId->value);

        $minor = $this->memberById($household, MemberId::fromString(self::MINOR_MEMBER_ID));
        self::assertFalse($minor->isSharedWith(HouseholdId::fromString(self::TARGET_HOUSEHOLD_ID)));
        self::assertSame([], $minor->sharedHouseholdIds());
    }

    #[Test]
    #[TestDox('::withdrawMemberFromHousehold() is a no-op (no event) when the member is not shared with the target.')]
    public function withdraw_member_from_household_is_noop_when_not_shared(): void
    {
        $household = $this->registerWithMinorMember();

        $household->withdrawMemberFromHousehold(
            MemberId::fromString(self::MINOR_MEMBER_ID),
            HouseholdId::fromString(self::TARGET_HOUSEHOLD_ID),
            $this->clock,
        );

        self::assertSame([], $household->releaseEvents());
    }

    private function registerWithMinorMember(): Household
    {
        $household = $this->register();
        $household->addMember(
            MemberId::fromString(self::MINOR_MEMBER_ID),
            MemberCode::of('M0004'),
            PersonName::of('Timmy', 'Smith'),
            DateOfBirth::of(new DateTimeImmutable('2015-01-01'), $this->clock),
            Gender::Male,
            null,
            null,
            ResidencyStatus::Resident,
            false,
            $this->clock,
        );
        $household->releaseEvents();

        return $household;
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

    /**
     * Same fixture as {@see self::register()}, with the primary member's
     * salutation, height, and weight (LRA-205) populated so anonymization
     * tests can assert those fields are cleared rather than trivially
     * staying null.
     */
    private function registerWithSalutationHeightAndWeight(): Household
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
            Salutation::Ms,
            Height::ofInches(65),
            Weight::ofPounds(140),
        );
    }
}
