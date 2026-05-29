<?php

declare(strict_types=1);

namespace App\Tests\Support\Trait;

use App\Households\Domain\Exception\HouseholdNotFound;
use App\Households\Domain\Household;
use App\Households\Domain\Households;
use App\Households\Domain\MemberCodeAllocator;
use App\Households\Domain\ValueObject\Address;
use App\Households\Domain\ValueObject\DateOfBirth;
use App\Shared\Domain\ValueObject\EmailAddress;
use App\Households\Domain\ValueObject\Gender;
use App\Households\Domain\ValueObject\HouseholdId;
use App\Households\Domain\ValueObject\HouseholdName;
use App\Households\Domain\ValueObject\MemberCode;
use App\Households\Domain\ValueObject\MemberId;
use App\Households\Domain\ValueObject\PersonName;
use App\Shared\Domain\ValueObject\PhoneNumber;
use App\Households\Domain\ValueObject\ResidencyStatus;
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
        self::assertTrue($loaded->name()->equals(HouseholdName::of('Smith Family')));
        self::assertTrue($loaded->address()->equals($this->address()));

        $members = $loaded->members();
        self::assertCount(2, $members);

        $byId = [];
        foreach ($members as $member) {
            $byId[$member->id()->value] = $member;
        }

        $primary = $byId[self::PRIMARY_MEMBER_ID];
        self::assertTrue($primary->code()->equals(MemberCode::of(self::PRIMARY_MEMBER_CODE)));
        self::assertTrue($primary->name()->equals(PersonName::of('Alice', 'Smith')));
        self::assertSame(Gender::Female, $primary->gender());
        self::assertNotNull($primary->email());
        self::assertTrue($primary->email()->equals(EmailAddress::of('alice@example.com')));
        self::assertNull($primary->phone());
        self::assertSame(ResidencyStatus::Resident, $primary->residencyStatus());
        self::assertTrue($primary->isPrimary());
        self::assertTrue($primary->isActive());

        $second = $byId[self::SECOND_MEMBER_ID];
        self::assertTrue($second->code()->equals(MemberCode::of(self::SECOND_MEMBER_CODE)));
        self::assertTrue($second->name()->equals(PersonName::of('Bob', 'Smith', 'Quincy', 'Jr.')));
        self::assertSame(Gender::Male, $second->gender());
        self::assertNull($second->email());
        self::assertNotNull($second->phone());
        self::assertTrue($second->phone()->equals(PhoneNumber::of('5550002')));
        self::assertSame(ResidencyStatus::NonResident, $second->residencyStatus());
        self::assertFalse($second->isPrimary());
        self::assertTrue($second->isActive());
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
            EmailAddress::of('alice.new@example.com'),
            PhoneNumber::of('5550111'),
            $this->clock(),
        );

        $loaded->setResidencyStatus(
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
        self::assertNotNull($primary->email());
        self::assertTrue($primary->email()->equals(EmailAddress::of('alice.new@example.com')));
        self::assertNotNull($primary->phone());
        self::assertTrue($primary->phone()->equals(PhoneNumber::of('5550111')));

        $second = $byId[self::SECOND_MEMBER_ID];
        self::assertSame(ResidencyStatus::Member, $second->residencyStatus());
        self::assertFalse($second->isActive());
        $deactivation = $second->deactivation();
        self::assertNotNull($deactivation);
        self::assertSame('moved out of state', $deactivation->reason);
        self::assertInstanceOf(\DateTimeImmutable::class, $deactivation->at);
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

    private function buildHouseholdWithTwoMembers(): Household
    {
        $household = Household::register(
            HouseholdId::fromString(self::HOUSEHOLD_ID),
            HouseholdName::of('Smith Family'),
            $this->address(),
            MemberId::fromString(self::PRIMARY_MEMBER_ID),
            MemberCode::of(self::PRIMARY_MEMBER_CODE),
            PersonName::of('Alice', 'Smith'),
            DateOfBirth::of(new DateTimeImmutable('1990-01-01'), $this->clock()),
            Gender::Female,
            EmailAddress::of('alice@example.com'),
            null,
            ResidencyStatus::Resident,
            $this->clock(),
        );

        $household->addMember(
            MemberId::fromString(self::SECOND_MEMBER_ID),
            MemberCode::of(self::SECOND_MEMBER_CODE),
            PersonName::of('Bob', 'Smith', 'Quincy', 'Jr.'),
            DateOfBirth::of(new DateTimeImmutable('1992-03-04'), $this->clock()),
            Gender::Male,
            null,
            PhoneNumber::of('5550002'),
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
}
