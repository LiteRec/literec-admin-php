<?php

declare(strict_types=1);

namespace App\Tests\Unit\Households\Domain;

use App\Households\Domain\Event\HouseholdAddressUpdated;
use App\Households\Domain\Event\HouseholdRegistered;
use App\Households\Domain\Event\MemberAddedToHousehold;
use App\Households\Domain\Event\MemberAnonymized;
use App\Households\Domain\Event\MemberPhotoReleased;
use App\Households\Domain\Event\MemberRemovedFromHousehold;
use App\Households\Domain\Event\MemberSplitOff;
use App\Households\Domain\Exception\DuplicateMemberCode;
use App\Households\Domain\Exception\DuplicateMemberId;
use App\Households\Domain\Exception\InvariantViolation;
use App\Households\Domain\Exception\MemberAlreadyMerged;
use App\Households\Domain\Exception\MemberIsAnonymized;
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
use App\Households\Domain\ValueObject\MemberContact;
use App\Households\Domain\ValueObject\MemberId;
use App\Households\Domain\ValueObject\MemberProfile;
use App\Households\Domain\ValueObject\PersonName;
use App\Households\Domain\ValueObject\ProfilePhoto;
use App\Shared\Domain\ValueObject\PhoneNumber;
use App\Households\Domain\ValueObject\ResidencyStatus;
use App\Households\Domain\ValueObject\Salutation;
use App\Households\Domain\ValueObject\TransactionReferences;
use App\Households\Domain\ValueObject\Weight;
use App\Tests\Support\Trait\RegistersSmithHousehold;
use DateTimeImmutable;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\Clock\MockClock;

#[Small]
final class HouseholdTest extends TestCase
{
    use RegistersSmithHousehold;

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
            MemberProfile::of(
                PersonName::of('Bob', 'Smith'),
                DateOfBirth::of(new DateTimeImmutable('1992-03-04'), $this->clock),
                Gender::Male,
            ),
            MemberContact::of(EmailAddress::of('bob@example.com'), PhoneNumber::of('5550002')),
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
    }

    #[Test]
    #[TestDox('::member() throws MemberNotFound for an unknown member id.')]
    public function member_throws_when_member_unknown(): void
    {
        $household = $this->register();

        $this->expectException(MemberNotFound::class);

        $household->member(MemberId::fromString('019571bf-5d51-7000-b500-bbbbbbbbbbbb'));
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
        $profile = $member->profile();
        $contact = $member->contact();
        self::assertTrue($member->id()->equals($memberId));
        self::assertTrue($member->code()->equals($originalCode));
        self::assertSame('Anonymized', $profile->name->firstName);
        self::assertSame('Member', $profile->name->lastName);
        self::assertSame('1900-01-01', $profile->dateOfBirth->value->format('Y-m-d'));
        self::assertSame(Gender::Unspecified, $profile->gender);
        self::assertNull($contact->email);
        self::assertNull($contact->phone);
        self::assertNull($profile->salutation);
        self::assertNull($profile->height);
        self::assertNull($profile->weight);
    }

    #[Test]
    #[TestDox('::anonymizeMember() releases an attached photo, recording MemberPhotoReleased for its storage key.')]
    public function anonymize_member_releases_attached_photo(): void
    {
        $household = $this->register();
        $memberId = MemberId::fromString(self::PRIMARY_MEMBER_ID);
        $photo = $this->photo('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa.jpg');
        $household->member($memberId)->attachPhoto($photo, $this->clock);
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

        $lifecycle = $this->memberById($household, $memberId)->lifecycle();
        self::assertFalse($lifecycle->isActive);
        self::assertSame('Anonymized', $lifecycle->deactivation?->reason);
        self::assertTrue($lifecycle->isAnonymized());
        self::assertEquals($this->clock->now(), $lifecycle->anonymizedAt);
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
            MemberProfile::of(
                PersonName::of('Bob', 'Smith'),
                DateOfBirth::of(new DateTimeImmutable('1992-01-01'), $this->clock),
                Gender::Male,
            ),
            MemberContact::none(),
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
            MemberProfile::of(
                PersonName::of('Bob', 'Smith'),
                DateOfBirth::of(new DateTimeImmutable('1992-01-01'), $this->clock),
                Gender::Male,
            ),
            MemberContact::none(),
            ResidencyStatus::Resident,
            false,
            $this->clock,
        );
        $household->member(MemberId::fromString(self::SECOND_MEMBER_ID))->mergeInto(
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

    #[Test]
    #[TestDox('::lifecycle() throws InvariantViolation when mergedIntoMemberId is set without mergedAt.')]
    public function lifecycle_throws_when_merged_into_member_id_set_without_merged_at(): void
    {
        $household = $this->register();
        $member = $this->memberById($household, MemberId::fromString(self::PRIMARY_MEMBER_ID));

        // markMergedInto() always writes mergedIntoMemberId and mergedAt
        // together; force the split state directly to prove lifecycle()
        // refuses to silently treat it as unmerged.
        $property = new ReflectionProperty($member, 'mergedIntoMemberId');
        $property->setValue($member, MemberId::fromString('019571bf-5d51-7000-b500-000000000098'));

        $this->expectException(InvariantViolation::class);

        $member->lifecycle();
    }

    /**
     * @return Generator<string, array{mutate: callable(Household, MemberId, MockClock): void}>
     */
    public static function mergedMemberMutatorCases(): Generator
    {
        yield 'removeMember' => ['mutate' => static function (Household $h, MemberId $id, MockClock $clock): void {
            $h->removeMember($id, $clock);
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
    #[TestDox('removeMember() and splitMember() throw MemberAlreadyMerged when the target member is merged.')]
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
        $sourceProfile = $source->profile();
        $newProfile = $new->profile();
        $newContact = $new->contact();
        self::assertTrue($newProfile->dateOfBirth->equals($sourceProfile->dateOfBirth));
        self::assertSame($sourceProfile->gender, $newProfile->gender);
        self::assertSame($source->residencyStatus(), $new->residencyStatus());
        self::assertSame('Bob', $newProfile->name->firstName);
        self::assertNotNull($newContact->email);
        self::assertTrue($newContact->email->equals(EmailAddress::of('bob@example.com')));
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
        $household->member($sourceId)->deactivate('moved away', $this->clock);
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
    #[TestDox('members() clones carry the photo alongside every other member field.')]
    public function members_clone_carries_photo(): void
    {
        $household = $this->register();
        $memberId = MemberId::fromString(self::PRIMARY_MEMBER_ID);
        $photo = $this->photo('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa.jpg');
        $household->member($memberId)->attachPhoto($photo, $this->clock);

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
    #[TestDox('::removeMember() records MemberRemovedFromHousehold for a known member.')]
    public function remove_member_records_event(): void
    {
        $household = $this->register();
        $household->releaseEvents();

        $secondMemberId = MemberId::fromString('019571bf-5d51-7000-b500-fedcba987654');
        $household->addMember(
            $secondMemberId,
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
    #[TestDox('releaseEvents() returns the buffer and clears it.')]
    public function release_events_clears_buffer(): void
    {
        $household = $this->register();

        self::assertCount(2, $household->releaseEvents());
        self::assertSame([], $household->releaseEvents());
    }

    private function register(): Household
    {
        return $this->registerSmithHousehold(self::HOUSEHOLD_ID, self::PRIMARY_MEMBER_ID);
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
            MemberProfile::of(
                PersonName::of('Alice', 'Smith'),
                DateOfBirth::of(new DateTimeImmutable('1990-01-01'), $this->clock),
                Gender::Female,
                Salutation::Ms,
                Height::ofInches(65),
                Weight::ofPounds(140),
            ),
            MemberContact::of(EmailAddress::of('alice@example.com'), PhoneNumber::of('5550001')),
            ResidencyStatus::Resident,
            $this->clock,
        );
    }
}
