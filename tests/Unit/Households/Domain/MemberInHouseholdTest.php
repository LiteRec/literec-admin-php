<?php

declare(strict_types=1);

namespace App\Tests\Unit\Households\Domain;

use App\Households\Domain\Event\MemberContactUpdated;
use App\Households\Domain\Event\MemberDeactivated;
use App\Households\Domain\Event\MemberMergedInto;
use App\Households\Domain\Event\MemberPhotoAttached;
use App\Households\Domain\Event\MemberPhotoReleased;
use App\Households\Domain\Event\MemberPhotoRemoved;
use App\Households\Domain\Event\MemberProfileUpdated;
use App\Households\Domain\Event\MemberReactivated;
use App\Households\Domain\Event\MemberResidencyChanged;
use App\Households\Domain\Event\MemberSharedWithHousehold;
use App\Households\Domain\Event\MemberSharingWithdrawn;
use App\Households\Domain\Exception\CannotMergeMemberIntoItself;
use App\Households\Domain\Exception\CannotShareWithHomeHousehold;
use App\Households\Domain\Exception\HouseholdAlreadyLinked;
use App\Households\Domain\Exception\InvariantViolation;
use App\Households\Domain\Exception\MemberAlreadyMerged;
use App\Households\Domain\Exception\MemberIsAnonymized;
use App\Households\Domain\Exception\MemberNotAMinor;
use App\Households\Domain\Household;
use App\Households\Domain\HouseholdMember;
use App\Households\Domain\ValueObject\AnonymizedProfile;
use App\Households\Domain\ValueObject\DateOfBirth;
use App\Households\Domain\ValueObject\Gender;
use App\Households\Domain\ValueObject\Height;
use App\Households\Domain\ValueObject\HouseholdId;
use App\Households\Domain\ValueObject\ImageFormat;
use App\Households\Domain\ValueObject\MemberCode;
use App\Households\Domain\ValueObject\MemberContact;
use App\Households\Domain\ValueObject\MemberId;
use App\Households\Domain\ValueObject\MemberProfile;
use App\Households\Domain\ValueObject\PersonName;
use App\Households\Domain\ValueObject\ProfilePhoto;
use App\Households\Domain\ValueObject\ResidencyStatus;
use App\Households\Domain\ValueObject\Salutation;
use App\Households\Domain\ValueObject\Weight;
use App\Shared\Domain\ValueObject\EmailAddress;
use App\Shared\Domain\ValueObject\PhoneNumber;
use App\Tests\Support\Trait\RegistersSmithHousehold;
use DateTimeImmutable;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

/**
 * Per-member operations reached through {@see Household::member()}. Register
 * / add / remove / address / anonymize / split — household-level state and
 * the member set itself — stay covered by {@see HouseholdTest}.
 */
#[Small]
final class MemberInHouseholdTest extends TestCase
{
    use RegistersSmithHousehold;

    private const string HOUSEHOLD_ID = '019571bf-5d51-7000-b500-000000000001';
    private const string PRIMARY_MEMBER_ID = '019571bf-5d51-7000-b500-000000000002';
    private const string MINOR_MEMBER_ID = '019571bf-5d51-7000-b500-000000000004';
    private const string TARGET_HOUSEHOLD_ID = '019571bf-5d51-7000-b500-000000000005';

    private MockClock $clock;

    protected function setUp(): void
    {
        $this->clock = new MockClock(new DateTimeImmutable('2026-01-01 12:00:00'));
    }

    #[Test]
    #[TestDox('::updateProfile() is a no-op when nothing changed (no event recorded).')]
    public function update_member_profile_is_noop_when_unchanged(): void
    {
        $household = $this->register();
        $household->releaseEvents();

        $household->member(MemberId::fromString(self::PRIMARY_MEMBER_ID))->updateProfile(
            MemberProfile::of(
                PersonName::of('Alice', 'Smith'),
                DateOfBirth::of(new DateTimeImmutable('1990-01-01'), $this->clock),
                Gender::Female,
            ),
            $this->clock,
        );

        self::assertSame([], $household->releaseEvents());
    }

    #[Test]
    #[TestDox('::updateProfile() records MemberProfileUpdated on a real change.')]
    public function update_member_profile_records_event_on_change(): void
    {
        $household = $this->register();
        $household->releaseEvents();

        $household->member(MemberId::fromString(self::PRIMARY_MEMBER_ID))->updateProfile(
            MemberProfile::of(
                PersonName::of('Alice', 'Johnson'),
                DateOfBirth::of(new DateTimeImmutable('1990-01-01'), $this->clock),
                Gender::Female,
            ),
            $this->clock,
        );

        $events = $household->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(MemberProfileUpdated::class, $events[0]);
        self::assertSame(self::PRIMARY_MEMBER_ID, $events[0]->memberId->value);
    }

    #[Test]
    #[TestDox('::updateProfile() records MemberProfileUpdated on a salutation/height/weight-only change.')]
    public function update_member_profile_records_event_on_measurement_only_change(): void
    {
        $household = $this->register();
        $household->releaseEvents();

        $household->member(MemberId::fromString(self::PRIMARY_MEMBER_ID))->updateProfile(
            MemberProfile::of(
                PersonName::of('Alice', 'Smith'),
                DateOfBirth::of(new DateTimeImmutable('1990-01-01'), $this->clock),
                Gender::Female,
                Salutation::Ms,
                Height::ofInches(65),
                Weight::ofPounds(140),
            ),
            $this->clock,
        );

        $events = $household->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(MemberProfileUpdated::class, $events[0]);
    }

    #[Test]
    #[TestDox('::updateProfile() records MemberProfileUpdated when only the nickname changes.')]
    public function update_member_profile_records_event_on_nickname_only_change(): void
    {
        $household = $this->register();
        $household->releaseEvents();

        $household->member(MemberId::fromString(self::PRIMARY_MEMBER_ID))->updateProfile(
            MemberProfile::of(
                PersonName::of('Alice', 'Smith', nickname: 'Al'),
                DateOfBirth::of(new DateTimeImmutable('1990-01-01'), $this->clock),
                Gender::Female,
            ),
            $this->clock,
        );

        $events = $household->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(MemberProfileUpdated::class, $events[0]);
    }

    #[Test]
    #[TestDox('::updateProfile() is a no-op when salutation, height, and weight are resubmitted unchanged.')]
    public function update_member_profile_is_noop_when_measurements_unchanged(): void
    {
        $household = $this->register();
        $profile = MemberProfile::of(
            PersonName::of('Alice', 'Smith'),
            DateOfBirth::of(new DateTimeImmutable('1990-01-01'), $this->clock),
            Gender::Female,
            Salutation::Ms,
            Height::ofInches(65),
            Weight::ofPounds(140),
        );
        $household->member(MemberId::fromString(self::PRIMARY_MEMBER_ID))->updateProfile($profile, $this->clock);
        $household->releaseEvents();

        $household->member(MemberId::fromString(self::PRIMARY_MEMBER_ID))->updateProfile($profile, $this->clock);

        self::assertSame([], $household->releaseEvents());
    }

    #[Test]
    #[TestDox('::updateContact() records MemberContactUpdated on a real change.')]
    public function update_contact_records_event_on_change(): void
    {
        $household = $this->register();
        $household->releaseEvents();

        $household->member(MemberId::fromString(self::PRIMARY_MEMBER_ID))->updateContact(
            MemberContact::of(EmailAddress::of('alice.new@example.com'), PhoneNumber::of('5559999')),
            $this->clock,
        );

        $events = $household->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(MemberContactUpdated::class, $events[0]);
    }

    #[Test]
    #[TestDox('::updateContact() with (null, null) clears both channels and records the event with null values.')]
    public function update_contact_clears_both_channels_when_given_null(): void
    {
        $household = $this->register();
        $household->releaseEvents();

        $household->member(MemberId::fromString(self::PRIMARY_MEMBER_ID))->updateContact(
            MemberContact::none(),
            $this->clock,
        );

        $events = $household->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(MemberContactUpdated::class, $events[0]);
        self::assertNull($events[0]->email);
        self::assertNull($events[0]->phone);
    }

    #[Test]
    #[TestDox('::updateContact() is a no-op when neither value changed.')]
    public function update_contact_is_noop_when_unchanged(): void
    {
        $household = $this->register();
        $household->releaseEvents();

        $household->member(MemberId::fromString(self::PRIMARY_MEMBER_ID))->updateContact(
            MemberContact::of(EmailAddress::of('alice@example.com'), PhoneNumber::of('5550001')),
            $this->clock,
        );

        self::assertSame([], $household->releaseEvents());
    }

    #[Test]
    #[TestDox('::fillContactGaps() fills only blank email/phone and records MemberContactUpdated.')]
    public function fill_member_contact_gaps_fills_only_blanks(): void
    {
        $household = $this->register();
        $secondId = MemberId::fromString('019571bf-5d51-7000-b500-000000000003');
        $household->addMember(
            $secondId,
            MemberCode::of('M0002'),
            MemberProfile::of(
                PersonName::of('Bob', 'Smith'),
                DateOfBirth::of(new DateTimeImmutable('1992-03-04'), $this->clock),
                Gender::Male,
            ),
            MemberContact::none(),
            ResidencyStatus::Resident,
            false,
            $this->clock,
        );
        $household->releaseEvents();

        $household->member($secondId)->fillContactGaps(
            EmailAddress::of('found@example.com'),
            PhoneNumber::of('5559999'),
            $this->clock,
        );

        $events = $household->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(MemberContactUpdated::class, $events[0]);
        $secondContact = $this->memberById($household, $secondId)->contact();
        self::assertNotNull($secondContact->email);
        self::assertTrue($secondContact->email->equals(EmailAddress::of('found@example.com')));
        self::assertNotNull($secondContact->phone);
        self::assertTrue($secondContact->phone->equals(PhoneNumber::of('5559999')));
    }

    #[Test]
    #[TestDox('::fillContactGaps() is silent when the survivor already has both fields populated.')]
    public function fill_member_contact_gaps_is_silent_when_nothing_blank(): void
    {
        $household = $this->register();
        $household->releaseEvents();
        $primaryId = MemberId::fromString(self::PRIMARY_MEMBER_ID);

        $household->member($primaryId)->fillContactGaps(
            EmailAddress::of('duplicate@example.com'),
            PhoneNumber::of('5551234'),
            $this->clock,
        );

        self::assertSame([], $household->releaseEvents());
        $primaryContact = $this->memberById($household, $primaryId)->contact();
        self::assertNotNull($primaryContact->email);
        self::assertTrue($primaryContact->email->equals(EmailAddress::of('alice@example.com')));
        self::assertNotNull($primaryContact->phone);
        self::assertTrue($primaryContact->phone->equals(PhoneNumber::of('5550001')));
    }

    #[Test]
    #[TestDox('::changeResidency() records MemberResidencyChanged with the effective date.')]
    public function change_residency_records_event(): void
    {
        $household = $this->register();
        $household->releaseEvents();
        $effectiveFrom = new DateTimeImmutable('2026-02-01');

        $household->member(MemberId::fromString(self::PRIMARY_MEMBER_ID))->changeResidency(
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
    #[TestDox('::deactivate() records MemberDeactivated and is idempotent on a second call.')]
    public function deactivate_member_is_idempotent(): void
    {
        $household = $this->register();
        $household->releaseEvents();
        $member = $household->member(MemberId::fromString(self::PRIMARY_MEMBER_ID));

        $member->deactivate('moved away', $this->clock);

        $events = $household->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(MemberDeactivated::class, $events[0]);
        self::assertSame('moved away', $events[0]->reason);

        $member->deactivate('second reason', $this->clock);

        self::assertSame([], $household->releaseEvents());
    }

    #[Test]
    #[TestDox('::reactivate() records MemberReactivated only when previously deactivated.')]
    public function reactivate_member_records_event_only_when_deactivated(): void
    {
        $household = $this->register();
        $household->releaseEvents();
        $memberId = MemberId::fromString(self::PRIMARY_MEMBER_ID);

        // No-op when already active.
        $household->member($memberId)->reactivate($this->clock);
        $noopEvents = $household->releaseEvents();
        self::assertSame([], $noopEvents);

        // After deactivation, reactivate records the event.
        $household->member($memberId)->deactivate('pause', $this->clock);
        $household->releaseEvents();

        $household->member($memberId)->reactivate($this->clock);

        $events = $household->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(MemberReactivated::class, $events[0]);
    }

    /**
     * Shared by {@see self::anonymizedMemberMutatorCases()} and
     * {@see self::mergedMemberMutatorCases()}: both guards refuse
     * ::updateProfile() the same way, so the two data providers reuse one
     * mutator instead of duplicating its body.
     */
    private static function updateProfileMutator(): callable
    {
        return static function (Household $h, MemberId $id, MockClock $clock): void {
            $h->member($id)->updateProfile(
                MemberProfile::of(
                    PersonName::of('Changed', 'Name'),
                    DateOfBirth::of(new DateTimeImmutable('1990-01-01'), $clock),
                    Gender::Male,
                ),
                $clock,
            );
        };
    }

    /**
     * Shared by {@see self::anonymizedMemberMutatorCases()} and
     * {@see self::mergedMemberMutatorCases()} for the same reason as
     * {@see self::updateProfileMutator()}.
     */
    private static function updateContactMutator(): callable
    {
        return static function (Household $h, MemberId $id, MockClock $clock): void {
            $h->member($id)->updateContact(MemberContact::of(EmailAddress::of('new@example.com'), null), $clock);
        };
    }

    /**
     * @return Generator<string, array{mutate: callable(Household, MemberId, MockClock): void}>
     */
    public static function anonymizedMemberMutatorCases(): Generator
    {
        yield 'updateProfile' => ['mutate' => self::updateProfileMutator()];
        yield 'updateContact' => ['mutate' => self::updateContactMutator()];
        yield 'reactivate' => ['mutate' => static function (Household $h, MemberId $id, MockClock $clock): void {
            $h->member($id)->reactivate($clock);
        }];
        yield 'changeResidency' => ['mutate' => static function (Household $h, MemberId $id, MockClock $clock): void {
            $h->member($id)->changeResidency(ResidencyStatus::NonResident, $clock->now(), $clock);
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

    /**
     * @return Generator<string, array{mutate: callable(Household, MemberId, MockClock): void}>
     */
    public static function mergedMemberMutatorCases(): Generator
    {
        yield 'updateProfile' => ['mutate' => self::updateProfileMutator()];
        yield 'updateContact' => ['mutate' => self::updateContactMutator()];
        yield 'changeResidency' => ['mutate' => static function (Household $h, MemberId $id, MockClock $clock): void {
            $h->member($id)->changeResidency(ResidencyStatus::Member, $clock->now(), $clock);
        }];
        yield 'deactivate' => ['mutate' => static function (Household $h, MemberId $id, MockClock $clock): void {
            $h->member($id)->deactivate('reason', $clock);
        }];
        yield 'reactivate' => ['mutate' => static function (Household $h, MemberId $id, MockClock $clock): void {
            $h->member($id)->reactivate($clock);
        }];
        yield 'attachPhoto' => ['mutate' => static function (Household $h, MemberId $id, MockClock $clock): void {
            $photo = ProfilePhoto::of(
                $id->value . '/aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa.jpg',
                ImageFormat::Jpeg,
                $clock->now(),
            );
            $h->member($id)->attachPhoto($photo, $clock);
        }];
        yield 'removePhoto' => ['mutate' => static function (Household $h, MemberId $id, MockClock $clock): void {
            $h->member($id)->removePhoto($clock);
        }];
    }

    #[Test]
    #[DataProvider('mergedMemberMutatorCases')]
    #[TestDox('every other mutator throws MemberAlreadyMerged when the target member is merged.')]
    public function mutators_throw_member_already_merged_on_merged_member(callable $mutate): void
    {
        $household = $this->register();
        $memberId = MemberId::fromString(self::PRIMARY_MEMBER_ID);
        $household->member($memberId)->mergeInto(
            HouseholdId::fromString('019571bf-5d51-7000-b500-000000000099'),
            MemberId::fromString('019571bf-5d51-7000-b500-000000000098'),
            $this->clock,
        );
        $household->releaseEvents();

        $this->expectException(MemberAlreadyMerged::class);

        $mutate($household, $memberId, $this->clock);
    }

    #[Test]
    #[TestDox('::mergeInto() records MemberMergedInto carrying the duplicate\'s contact and marks it merged.')]
    public function merge_member_into_records_event_with_contact(): void
    {
        $household = $this->register();
        $household->releaseEvents();
        $duplicateId = MemberId::fromString(self::PRIMARY_MEMBER_ID);
        $survivorHouseholdId = HouseholdId::fromString('019571bf-5d51-7000-b500-000000000099');
        $survivorId = MemberId::fromString('019571bf-5d51-7000-b500-000000000098');

        $household->member($duplicateId)->mergeInto($survivorHouseholdId, $survivorId, $this->clock);

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

        $mergedLifecycle = $this->memberById($household, $duplicateId)->lifecycle();
        self::assertTrue($mergedLifecycle->isMerged());
        $merge = $mergedLifecycle->merge;
        self::assertNotNull($merge);
        self::assertTrue($merge->intoMemberId->equals($survivorId));
    }

    #[Test]
    #[TestDox('::mergeInto() throws CannotMergeMemberIntoItself when the duplicate and survivor ids match.')]
    public function merge_member_into_rejects_self_merge(): void
    {
        $household = $this->register();
        $memberId = MemberId::fromString(self::PRIMARY_MEMBER_ID);

        $this->expectException(CannotMergeMemberIntoItself::class);

        $household->member($memberId)->mergeInto(HouseholdId::fromString(self::HOUSEHOLD_ID), $memberId, $this->clock);
    }

    #[Test]
    #[TestDox('::mergeInto() throws MemberAlreadyMerged when the duplicate is already merged.')]
    public function merge_member_into_rejects_already_merged_duplicate(): void
    {
        $household = $this->register();
        $duplicateId = MemberId::fromString(self::PRIMARY_MEMBER_ID);
        $survivorId = MemberId::fromString('019571bf-5d51-7000-b500-000000000098');
        $household->member($duplicateId)->mergeInto(
            HouseholdId::fromString('019571bf-5d51-7000-b500-000000000099'),
            $survivorId,
            $this->clock,
        );
        $household->releaseEvents();

        $this->expectException(MemberAlreadyMerged::class);

        $household->member($duplicateId)->mergeInto(
            HouseholdId::fromString('019571bf-5d51-7000-b500-000000000099'),
            $survivorId,
            $this->clock,
        );
    }

    #[Test]
    #[TestDox('::attachPhoto() records MemberPhotoAttached and attaches the photo to the member.')]
    public function attach_member_photo_records_event_and_attaches_photo(): void
    {
        $household = $this->register();
        $household->releaseEvents();
        $memberId = MemberId::fromString(self::PRIMARY_MEMBER_ID);
        $photo = $this->photo('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa.jpg');

        $household->member($memberId)->attachPhoto($photo, $this->clock);

        $events = $household->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(MemberPhotoAttached::class, $events[0]);
        self::assertSame($photo->storageKey, $events[0]->storageKey);

        $member = $this->memberById($household, $memberId);
        self::assertNotNull($member->photo());
        self::assertTrue($member->photo()->equals($photo));
    }

    #[Test]
    #[TestDox('::attachPhoto() replacing a photo also records MemberPhotoReleased for the superseded key.')]
    public function attach_member_photo_replacing_releases_previous_key(): void
    {
        $household = $this->register();
        $memberId = MemberId::fromString(self::PRIMARY_MEMBER_ID);
        $first = $this->photo('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa.jpg');
        $household->member($memberId)->attachPhoto($first, $this->clock);
        $household->releaseEvents();

        $second = $this->photo('bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb.png');
        $household->member($memberId)->attachPhoto($second, $this->clock);

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
    #[TestDox('::removePhoto() is a no-op when the member has no photo.')]
    public function remove_member_photo_is_no_op_without_photo(): void
    {
        $household = $this->register();
        $household->releaseEvents();

        $household->member(MemberId::fromString(self::PRIMARY_MEMBER_ID))->removePhoto($this->clock);

        self::assertSame([], $household->releaseEvents());
    }

    #[Test]
    #[TestDox('::removePhoto() records MemberPhotoRemoved and MemberPhotoReleased and clears the photo.')]
    public function remove_member_photo_records_events_and_clears_photo(): void
    {
        $household = $this->register();
        $memberId = MemberId::fromString(self::PRIMARY_MEMBER_ID);
        $photo = $this->photo('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa.jpg');
        $household->member($memberId)->attachPhoto($photo, $this->clock);
        $household->releaseEvents();

        $household->member($memberId)->removePhoto($this->clock);

        $events = $household->releaseEvents();
        self::assertCount(2, $events);
        self::assertInstanceOf(MemberPhotoRemoved::class, $events[0]);
        self::assertInstanceOf(MemberPhotoReleased::class, $events[1]);
        self::assertSame($photo->storageKey, $events[1]->storageKey);

        self::assertNull($this->memberById($household, $memberId)->photo());
    }

    #[Test]
    #[TestDox('::shareWithHousehold() records MemberSharedWithHousehold and updates householdLinks().')]
    public function share_member_with_household_records_event_and_updates_shared_ids(): void
    {
        $household = $this->registerWithMinorMember();
        $household->releaseEvents();

        $household->member(MemberId::fromString(self::MINOR_MEMBER_ID))->shareWithHousehold(
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

        $minorLinks = $this->memberById($household, MemberId::fromString(self::MINOR_MEMBER_ID))->householdLinks();
        self::assertTrue($minorLinks->includes(HouseholdId::fromString(self::TARGET_HOUSEHOLD_ID)));
        self::assertEquals(
            [HouseholdId::fromString(self::TARGET_HOUSEHOLD_ID)],
            $minorLinks->householdIds(),
        );
        self::assertEquals(
            $this->clock->now(),
            $minorLinks->linkedAt(HouseholdId::fromString(self::TARGET_HOUSEHOLD_ID)),
        );
        self::assertNull($minorLinks->linkedAt(HouseholdId::fromString(self::HOUSEHOLD_ID)));
    }

    #[Test]
    #[TestDox('::shareWithHousehold() throws InvariantViolation for a deactivated member.')]
    public function share_member_throws_for_inactive_member(): void
    {
        $household = $this->registerWithMinorMember();
        $household->member(MemberId::fromString(self::MINOR_MEMBER_ID))->deactivate('moved away', $this->clock);

        $this->expectException(InvariantViolation::class);

        $household->member(MemberId::fromString(self::MINOR_MEMBER_ID))->shareWithHousehold(
            HouseholdId::fromString(self::TARGET_HOUSEHOLD_ID),
            $this->clock,
        );
    }

    #[Test]
    #[TestDox('::shareWithHousehold() throws CannotShareWithHomeHousehold when the target is this household.')]
    public function share_member_throws_when_target_is_home_household(): void
    {
        $household = $this->registerWithMinorMember();

        $this->expectException(CannotShareWithHomeHousehold::class);

        $household->member(MemberId::fromString(self::MINOR_MEMBER_ID))->shareWithHousehold(
            HouseholdId::fromString(self::HOUSEHOLD_ID),
            $this->clock,
        );
    }

    #[Test]
    #[TestDox('::shareWithHousehold() throws MemberNotAMinor for a member who is 18 or older.')]
    public function share_member_throws_for_adult_member(): void
    {
        $household = $this->registerWithMinorMember();

        $this->expectException(MemberNotAMinor::class);

        $household->member(MemberId::fromString(self::PRIMARY_MEMBER_ID))->shareWithHousehold(
            HouseholdId::fromString(self::TARGET_HOUSEHOLD_ID),
            $this->clock,
        );
    }

    #[Test]
    #[TestDox('::shareWithHousehold() throws HouseholdAlreadyLinked when already shared with the target.')]
    public function share_member_throws_when_already_linked(): void
    {
        $household = $this->registerWithMinorMember();
        $household->member(MemberId::fromString(self::MINOR_MEMBER_ID))->shareWithHousehold(
            HouseholdId::fromString(self::TARGET_HOUSEHOLD_ID),
            $this->clock,
        );

        $this->expectException(HouseholdAlreadyLinked::class);

        $household->member(MemberId::fromString(self::MINOR_MEMBER_ID))->shareWithHousehold(
            HouseholdId::fromString(self::TARGET_HOUSEHOLD_ID),
            $this->clock,
        );
    }

    #[Test]
    #[TestDox('::withdrawFromHousehold() records MemberSharingWithdrawn and removes the target link.')]
    public function withdraw_member_from_household_records_event_and_removes_link(): void
    {
        $household = $this->registerWithMinorMember();
        $household->member(MemberId::fromString(self::MINOR_MEMBER_ID))->shareWithHousehold(
            HouseholdId::fromString(self::TARGET_HOUSEHOLD_ID),
            $this->clock,
        );
        $household->releaseEvents();

        $household->member(MemberId::fromString(self::MINOR_MEMBER_ID))->withdrawFromHousehold(
            HouseholdId::fromString(self::TARGET_HOUSEHOLD_ID),
            $this->clock,
        );

        $events = $household->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(MemberSharingWithdrawn::class, $events[0]);
        self::assertSame(self::TARGET_HOUSEHOLD_ID, $events[0]->sharedHouseholdId->value);

        $minorLinks = $this->memberById($household, MemberId::fromString(self::MINOR_MEMBER_ID))->householdLinks();
        self::assertFalse($minorLinks->includes(HouseholdId::fromString(self::TARGET_HOUSEHOLD_ID)));
        self::assertSame([], $minorLinks->householdIds());
    }

    #[Test]
    #[TestDox('::withdrawFromHousehold() is a no-op (no event) when the member is not shared with the target.')]
    public function withdraw_member_from_household_is_noop_when_not_shared(): void
    {
        $household = $this->registerWithMinorMember();

        $household->member(MemberId::fromString(self::MINOR_MEMBER_ID))->withdrawFromHousehold(
            HouseholdId::fromString(self::TARGET_HOUSEHOLD_ID),
            $this->clock,
        );

        self::assertSame([], $household->releaseEvents());
    }

    private function photo(string $basename): ProfilePhoto
    {
        return ProfilePhoto::of(
            self::PRIMARY_MEMBER_ID . '/' . $basename,
            ImageFormat::fromMimeType('image/' . (str_ends_with($basename, '.png') ? 'png' : 'jpeg')),
            $this->clock->now(),
        );
    }

    private function memberById(Household $household, MemberId $memberId): HouseholdMember
    {
        foreach ($household->members() as $member) {
            if ($member->id()->equals($memberId)) {
                return $member;
            }
        }
        self::fail('Member not found in household.');
    }

    private function registerWithMinorMember(): Household
    {
        $household = $this->register();
        $household->addMember(
            MemberId::fromString(self::MINOR_MEMBER_ID),
            MemberCode::of('M0004'),
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
        $household->releaseEvents();

        return $household;
    }

    private function register(): Household
    {
        return $this->registerSmithHousehold(self::HOUSEHOLD_ID, self::PRIMARY_MEMBER_ID);
    }
}
