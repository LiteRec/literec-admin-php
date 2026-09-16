<?php

declare(strict_types=1);

namespace App\Tests\Support\Trait;

use App\Households\Domain\Exception\HouseholdNotFound;
use App\Households\Domain\Exception\MemberAlreadyMerged;
use App\Households\Domain\Exception\MemberNotFound;
use App\Households\Domain\Household;
use App\Households\Domain\Households;
use App\Households\Domain\MemberCodeAllocator;
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
use App\Households\Domain\ValueObject\Weight;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Component\Clock\MockClock;

/**
 * Shared behavioral contract for any {@see Households} +
 * {@see MemberCodeAllocator} adapter pair. Concrete test classes
 * (`InMemoryHouseholdsContractTest`, `DoctrineHouseholdsContractTest`)
 * use this trait so the two implementations cannot drift apart.
 */
trait HouseholdsContractCases
{
    private const HOUSEHOLD_ID         = '019571bf-5d51-7000-b500-000000000001';
    private const PRIMARY_MEMBER_ID    = '019571bf-5d51-7000-b500-000000000002';
    private const SECOND_MEMBER_ID     = '019571bf-5d51-7000-b500-000000000003';
    private const PRIMARY_MEMBER_CODE  = 'M000001';
    private const SECOND_MEMBER_CODE   = 'M000002';
    private const MINOR_MEMBER_ID      = '019571bf-5d51-7000-b500-000000000004';
    private const MINOR_MEMBER_CODE    = 'M000003';
    private const TARGET_HOUSEHOLD_ID  = '019571bf-5d51-7000-b500-000000000005';
    private const HOUSEHOLD_NAME       = 'Smith Family';

    abstract protected function households(): Households;

    abstract protected function memberCodeAllocator(): MemberCodeAllocator;

    abstract protected function clock(): MockClock;

    #[Test]
    #[TestDox('save(): a populated household round-trips through findById() with deep-equal members and address.')]
    public function household_round_trips_through_save_and_find_by_id(): void
    {
        $household = $this->buildHouseholdWithTwoMembers();
        $this->households()->save($household);

        $loaded = $this->households()->findById(HouseholdId::fromString(self::HOUSEHOLD_ID));

        self::assertTrue($loaded->id()->equals(HouseholdId::fromString(self::HOUSEHOLD_ID)));
        self::assertTrue($loaded->name()->equals(HouseholdName::of(self::HOUSEHOLD_NAME)));
        self::assertTrue($loaded->address()->equals($this->address()));

        $members = $loaded->members();
        self::assertCount(2, $members);

        $byId = [];
        foreach ($members as $member) {
            $byId[$member->id()->value] = $member;
        }

        $primary = $byId[self::PRIMARY_MEMBER_ID];
        $primaryProfile = $primary->profile();
        $primaryContact = $primary->contact();
        self::assertTrue($primary->code()->equals(MemberCode::of(self::PRIMARY_MEMBER_CODE)));
        self::assertTrue($primaryProfile->name->equals(PersonName::of('Alice', 'Smith', nickname: 'Al')));
        self::assertSame(Gender::Female, $primaryProfile->gender);
        self::assertNotNull($primaryContact->email);
        self::assertTrue($primaryContact->email->equals(EmailAddress::of('alice@example.com')));
        self::assertNull($primaryContact->phone);
        self::assertSame(Salutation::Ms, $primaryProfile->salutation);
        self::assertNotNull($primaryProfile->height);
        self::assertTrue($primaryProfile->height->equals(Height::ofInches(65)));
        self::assertNotNull($primaryProfile->weight);
        self::assertTrue($primaryProfile->weight->equals(Weight::ofPounds(140)));
        self::assertSame(ResidencyStatus::Resident, $primary->residencyStatus());
        self::assertTrue($primary->isPrimary());
        self::assertTrue($primary->lifecycle()->isActive);

        $second = $byId[self::SECOND_MEMBER_ID];
        $secondProfile = $second->profile();
        $secondContact = $second->contact();
        self::assertTrue($second->code()->equals(MemberCode::of(self::SECOND_MEMBER_CODE)));
        self::assertTrue($secondProfile->name->equals(PersonName::of('Bob', 'Smith', 'Quincy', 'Jr.')));
        self::assertSame(Gender::Male, $secondProfile->gender);
        self::assertNull($secondContact->email);
        self::assertNotNull($secondContact->phone);
        self::assertTrue($secondContact->phone->equals(PhoneNumber::of('5550002')));
        self::assertNull($secondProfile->name->nickname);
        self::assertNull($secondProfile->salutation);
        self::assertNull($secondProfile->height);
        self::assertNull($secondProfile->weight);
        self::assertSame(ResidencyStatus::NonResident, $second->residencyStatus());
        self::assertFalse($second->isPrimary());
        self::assertTrue($second->lifecycle()->isActive);
    }

    #[Test]
    #[TestDox('findById(): throws HouseholdNotFound for an unknown id.')]
    public function find_by_id_throws_when_unknown(): void
    {
        $this->expectException(HouseholdNotFound::class);

        $this->households()->findById(
            HouseholdId::fromString('019571bf-5d51-7000-b500-0000000000ff'),
        );
    }

    #[Test]
    #[TestDox('findByMemberId(): returns the parent household, fully hydrated, for a known member id.')]
    public function find_by_member_id_returns_parent_household(): void
    {
        $household = $this->buildHouseholdWithTwoMembers();
        $this->households()->save($household);

        $loaded = $this->households()->findByMemberId(
            MemberId::fromString(self::SECOND_MEMBER_ID),
        );

        self::assertSame(self::HOUSEHOLD_ID, $loaded->id()->value);

        $loadedMemberIds = array_map(
            static fn($m): string => $m->id()->value,
            $loaded->members(),
        );
        sort($loadedMemberIds);
        $expected = [self::PRIMARY_MEMBER_ID, self::SECOND_MEMBER_ID];
        sort($expected);
        self::assertCount(2, $loadedMemberIds);
        self::assertSame($expected, $loadedMemberIds);
    }

    #[Test]
    #[TestDox('findByMemberId(): throws HouseholdNotFound when no household contains the member id.')]
    public function find_by_member_id_throws_when_unknown(): void
    {
        $this->expectException(HouseholdNotFound::class);

        $this->households()->findByMemberId(
            MemberId::fromString('019571bf-5d51-7000-b500-0000000000aa'),
        );
    }

    #[Test]
    #[TestDox('findByMemberCode(): returns the parent household, fully hydrated, for a known member code.')]
    public function find_by_member_code_returns_parent_household(): void
    {
        $household = $this->buildHouseholdWithTwoMembers();
        $this->households()->save($household);

        $loaded = $this->households()->findByMemberCode(
            MemberCode::of(self::SECOND_MEMBER_CODE),
        );

        self::assertSame(self::HOUSEHOLD_ID, $loaded->id()->value);

        $loadedMemberCodes = array_map(
            static fn($m): string => $m->code()->value,
            $loaded->members(),
        );
        sort($loadedMemberCodes);
        $expected = [self::PRIMARY_MEMBER_CODE, self::SECOND_MEMBER_CODE];
        sort($expected);
        self::assertCount(2, $loadedMemberCodes);
        self::assertSame($expected, $loadedMemberCodes);
    }

    #[Test]
    #[TestDox('findByMemberCode(): throws HouseholdNotFound when no household contains the member code.')]
    public function find_by_member_code_throws_when_unknown(): void
    {
        $this->expectException(HouseholdNotFound::class);

        $this->households()->findByMemberCode(MemberCode::of('M999999'));
    }

    #[Test]
    #[TestDox('save(): mutations on a loaded household persist on follow-up save (multiple fields).')]
    public function subsequent_mutations_persist(): void
    {
        $household = $this->buildHouseholdWithTwoMembers();
        $this->households()->save($household);

        $loaded = $this->households()->findById(HouseholdId::fromString(self::HOUSEHOLD_ID));

        $newAddress = Address::of('200 Oak Ave', null, 'Portland', 'OR', '97201', 'US');
        $loaded->updateAddress($newAddress, $this->clock());

        $loaded->updateMemberContact(
            MemberId::fromString(self::PRIMARY_MEMBER_ID),
            MemberContact::of(EmailAddress::of('alice.new@example.com'), PhoneNumber::of('5550111')),
            $this->clock(),
        );

        $loaded->changeMemberResidency(
            MemberId::fromString(self::SECOND_MEMBER_ID),
            ResidencyStatus::Member,
            $this->clock()->now(),
            $this->clock(),
            'paid annual membership',
        );

        $loaded->deactivateMember(
            MemberId::fromString(self::SECOND_MEMBER_ID),
            'moved out of state',
            $this->clock(),
        );

        $this->households()->save($loaded);

        $reloaded = $this->households()->findById(HouseholdId::fromString(self::HOUSEHOLD_ID));

        self::assertTrue($reloaded->address()->equals($newAddress));

        $byId = [];
        foreach ($reloaded->members() as $member) {
            $byId[$member->id()->value] = $member;
        }

        $primary = $byId[self::PRIMARY_MEMBER_ID];
        $primaryContact = $primary->contact();
        self::assertNotNull($primaryContact->email);
        self::assertTrue($primaryContact->email->equals(EmailAddress::of('alice.new@example.com')));
        self::assertNotNull($primaryContact->phone);
        self::assertTrue($primaryContact->phone->equals(PhoneNumber::of('5550111')));

        $second = $byId[self::SECOND_MEMBER_ID];
        $secondLifecycle = $second->lifecycle();
        self::assertSame(ResidencyStatus::Member, $second->residencyStatus());
        self::assertFalse($secondLifecycle->isActive);
        $deactivation = $secondLifecycle->deactivation;
        self::assertNotNull($deactivation);
        self::assertSame('moved out of state', $deactivation->reason);
        self::assertInstanceOf(\DateTimeImmutable::class, $deactivation->at);
    }

    #[Test]
    #[TestDox('save(): a member photo round-trips through findById() and a follow-up removal persists.')]
    public function member_photo_round_trips_through_save_and_find(): void
    {
        $household = $this->buildHouseholdWithTwoMembers();
        $this->households()->save($household);

        $loaded = $this->households()->findById(HouseholdId::fromString(self::HOUSEHOLD_ID));
        $photo = ProfilePhoto::of(
            self::PRIMARY_MEMBER_ID . '/aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa.jpg',
            ImageFormat::Jpeg,
            $this->clock()->now(),
        );
        $loaded->attachMemberPhoto(MemberId::fromString(self::PRIMARY_MEMBER_ID), $photo, $this->clock());
        $this->households()->save($loaded);

        $withPhoto = $this->households()->findById(HouseholdId::fromString(self::HOUSEHOLD_ID));
        $memberWithPhoto = $this->memberById($withPhoto, self::PRIMARY_MEMBER_ID);
        self::assertNotNull($memberWithPhoto->photo());
        self::assertTrue($memberWithPhoto->photo()->equals($photo));

        $withPhoto->removeMemberPhoto(MemberId::fromString(self::PRIMARY_MEMBER_ID), $this->clock());
        $this->households()->save($withPhoto);

        $withoutPhoto = $this->households()->findById(HouseholdId::fromString(self::HOUSEHOLD_ID));
        self::assertNull($this->memberById($withoutPhoto, self::PRIMARY_MEMBER_ID)->photo());
    }

    #[Test]
    #[TestDox('save(): a merged member round-trips through findById() with its survivor pointer intact.')]
    public function merged_member_round_trips_through_save_and_find(): void
    {
        $household = $this->buildHouseholdWithTwoMembers();
        $this->households()->save($household);

        $loaded = $this->households()->findById(HouseholdId::fromString(self::HOUSEHOLD_ID));
        $duplicateId = MemberId::fromString(self::SECOND_MEMBER_ID);
        $survivorId = MemberId::fromString(self::PRIMARY_MEMBER_ID);
        $survivorHouseholdId = HouseholdId::fromString(self::HOUSEHOLD_ID);
        $loaded->mergeMemberInto($duplicateId, $survivorHouseholdId, $survivorId, $this->clock());
        $this->households()->save($loaded);

        $reloaded = $this->households()->findById(HouseholdId::fromString(self::HOUSEHOLD_ID));
        $merged = $this->memberById($reloaded, self::SECOND_MEMBER_ID);
        $mergedLifecycle = $merged->lifecycle();
        self::assertTrue($mergedLifecycle->isMerged());
        $merge = $mergedLifecycle->merge;
        self::assertNotNull($merge);
        self::assertTrue($merge->intoMemberId->equals($survivorId));
        self::assertInstanceOf(DateTimeImmutable::class, $merge->at);
    }

    #[Test]
    #[TestDox('save(): an anonymized member round-trips through findById() with placeholder fields and anonymizedAt.')]
    public function anonymized_member_round_trips_with_placeholders_and_anonymized_at(): void
    {
        $household = $this->buildHouseholdWithTwoMembers();
        $this->households()->save($household);

        $loaded = $this->households()->findById(HouseholdId::fromString(self::HOUSEHOLD_ID));
        $loaded->anonymizeMember(
            MemberId::fromString(self::SECOND_MEMBER_ID),
            AnonymizedProfile::placeholder(),
            $this->clock(),
        );
        $this->households()->save($loaded);

        $reloaded = $this->households()->findById(HouseholdId::fromString(self::HOUSEHOLD_ID));
        $anonymized = $this->memberById($reloaded, self::SECOND_MEMBER_ID);
        $anonymizedLifecycle = $anonymized->lifecycle();
        $anonymizedContact = $anonymized->contact();
        self::assertTrue($anonymizedLifecycle->isAnonymized());
        self::assertInstanceOf(DateTimeImmutable::class, $anonymizedLifecycle->anonymizedAt);
        self::assertSame('Anonymized', $anonymized->profile()->name->firstName);
        self::assertSame('Member', $anonymized->profile()->name->lastName);
        self::assertNull($anonymizedContact->email);
        self::assertNull($anonymizedContact->phone);
        self::assertFalse($anonymizedLifecycle->isActive);

        // Alice (the primary) is untouched, and remains — the household is
        // not scrubbed while a non-anonymized member remains.
        self::assertTrue($reloaded->name()->equals(HouseholdName::of(self::HOUSEHOLD_NAME)));
    }

    #[Test]
    #[TestDox('MemberCodeAllocator::next(): codes match ^M\d{6}$ and consecutive calls return distinct values.')]
    public function member_code_allocator_returns_distinct_well_formed_codes(): void
    {
        $allocator = $this->memberCodeAllocator();

        $first = $allocator->next();
        $second = $allocator->next();

        self::assertMatchesRegularExpression('/^M\d{6}$/', $first->value);
        self::assertMatchesRegularExpression('/^M\d{6}$/', $second->value);
        self::assertNotSame($first->value, $second->value);
    }

    #[Test]
    #[TestDox('lockUnmergedMember(): does not throw for an unmerged member.')]
    public function lock_unmerged_member_passes_for_an_unmerged_member(): void
    {
        $household = $this->buildHouseholdWithTwoMembers();
        $repository = $this->households();
        $repository->save($household);

        $repository->lockUnmergedMember(
            HouseholdId::fromString(self::HOUSEHOLD_ID),
            MemberId::fromString(self::PRIMARY_MEMBER_ID),
        );

        // Reaching here without an exception is the point; also confirm the
        // lock did not itself mutate the member's merged state.
        $reloaded = $repository->findById(HouseholdId::fromString(self::HOUSEHOLD_ID));
        self::assertFalse($this->memberById($reloaded, self::PRIMARY_MEMBER_ID)->lifecycle()->isMerged());
    }

    #[Test]
    #[TestDox('lockUnmergedMember(): throws MemberNotFound for an unknown member id.')]
    public function lock_unmerged_member_throws_for_unknown_member(): void
    {
        $household = $this->buildHouseholdWithTwoMembers();
        $this->households()->save($household);

        $this->expectException(MemberNotFound::class);

        $this->households()->lockUnmergedMember(
            HouseholdId::fromString(self::HOUSEHOLD_ID),
            MemberId::fromString('019571bf-5d51-7000-b500-0000000000ff'),
        );
    }

    #[Test]
    #[TestDox('lockUnmergedMember(): throws MemberAlreadyMerged for an already-merged member.')]
    public function lock_unmerged_member_throws_for_an_already_merged_member(): void
    {
        $household = $this->buildHouseholdWithTwoMembers();
        $survivorId = MemberId::fromString(self::PRIMARY_MEMBER_ID);
        $duplicateId = MemberId::fromString(self::SECOND_MEMBER_ID);
        $householdId = HouseholdId::fromString(self::HOUSEHOLD_ID);
        $household->mergeMemberInto($duplicateId, $householdId, $survivorId, $this->clock());
        $this->households()->save($household);

        $this->expectException(MemberAlreadyMerged::class);

        $this->households()->lockUnmergedMember($householdId, $duplicateId);
    }

    #[Test]
    #[TestDox('save(): a member share round-trips through findById(), and a follow-up withdrawal persists.')]
    public function member_affiliation_round_trips_through_save_and_find(): void
    {
        $household = $this->buildHouseholdWithTwoMembers();
        $household->addMember(
            MemberId::fromString(self::MINOR_MEMBER_ID),
            MemberCode::of(self::MINOR_MEMBER_CODE),
            MemberProfile::of(
                PersonName::of('Charlie', 'Smith'),
                DateOfBirth::of(new DateTimeImmutable('2015-01-01'), $this->clock()),
                Gender::Male,
            ),
            MemberContact::none(),
            ResidencyStatus::Resident,
            false,
            $this->clock(),
        );
        $this->households()->save($household);
        $this->households()->save($this->buildTargetHousehold());

        $loaded = $this->households()->findById(HouseholdId::fromString(self::HOUSEHOLD_ID));
        $loaded->shareMemberWithHousehold(
            MemberId::fromString(self::MINOR_MEMBER_ID),
            HouseholdId::fromString(self::TARGET_HOUSEHOLD_ID),
            $this->clock(),
        );
        $this->households()->save($loaded);

        $reloaded = $this->households()->findById(HouseholdId::fromString(self::HOUSEHOLD_ID));
        $minor = $this->memberById($reloaded, self::MINOR_MEMBER_ID);
        self::assertTrue($minor->householdLinks()->includes(HouseholdId::fromString(self::TARGET_HOUSEHOLD_ID)));
        self::assertEquals(
            [HouseholdId::fromString(self::TARGET_HOUSEHOLD_ID)],
            $minor->householdLinks()->householdIds(),
        );

        $reloaded->withdrawMemberFromHousehold(
            MemberId::fromString(self::MINOR_MEMBER_ID),
            HouseholdId::fromString(self::TARGET_HOUSEHOLD_ID),
            $this->clock(),
        );
        $this->households()->save($reloaded);

        $final = $this->households()->findById(HouseholdId::fromString(self::HOUSEHOLD_ID));
        self::assertSame([], $this->memberById($final, self::MINOR_MEMBER_ID)->householdLinks()->householdIds());
    }

    private function buildTargetHousehold(): Household
    {
        return Household::register(
            HouseholdId::fromString(self::TARGET_HOUSEHOLD_ID),
            HouseholdName::of('Jones Family'),
            Address::of('500 Pine St', null, 'Tacoma', 'WA', '98402', 'US'),
            MemberId::fromString('019571bf-5d51-7000-b500-000000000006'),
            MemberCode::of('M000004'),
            MemberProfile::of(
                PersonName::of('Dana', 'Jones'),
                DateOfBirth::of(new DateTimeImmutable('1978-01-01'), $this->clock()),
                Gender::Female,
            ),
            MemberContact::of(EmailAddress::of('dana@example.com'), null),
            ResidencyStatus::Resident,
            $this->clock(),
        );
    }

    private function buildHouseholdWithTwoMembers(): Household
    {
        $household = Household::register(
            HouseholdId::fromString(self::HOUSEHOLD_ID),
            HouseholdName::of(self::HOUSEHOLD_NAME),
            $this->address(),
            MemberId::fromString(self::PRIMARY_MEMBER_ID),
            MemberCode::of(self::PRIMARY_MEMBER_CODE),
            MemberProfile::of(
                PersonName::of('Alice', 'Smith', nickname: 'Al'),
                DateOfBirth::of(new DateTimeImmutable('1990-01-01'), $this->clock()),
                Gender::Female,
                Salutation::Ms,
                Height::ofInches(65),
                Weight::ofPounds(140),
            ),
            MemberContact::of(EmailAddress::of('alice@example.com'), null),
            ResidencyStatus::Resident,
            $this->clock(),
        );

        $household->addMember(
            MemberId::fromString(self::SECOND_MEMBER_ID),
            MemberCode::of(self::SECOND_MEMBER_CODE),
            MemberProfile::of(
                PersonName::of('Bob', 'Smith', 'Quincy', 'Jr.'),
                DateOfBirth::of(new DateTimeImmutable('1992-03-04'), $this->clock()),
                Gender::Male,
            ),
            MemberContact::of(null, PhoneNumber::of('5550002')),
            ResidencyStatus::NonResident,
            false,
            $this->clock(),
        );

        return $household;
    }

    private function address(): Address
    {
        return Address::of('100 Main St', 'Apt 2B', 'Seattle', 'WA', '98101', 'US');
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
}
