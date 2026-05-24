<?php

declare(strict_types=1);

namespace App\Tests\Support\Trait;

use App\Households\Application\Query\Port\MemberReadModel;
use App\Households\Application\Query\Port\SearchMembersCriteria;
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
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Component\Clock\MockClock;

/**
 * Shared behavioral contract for any {@see MemberReadModel} adapter.
 * Concrete subclasses (InMemoryMemberReadModelContractTest,
 * DoctrineMemberReadModelContractTest) use this trait so the two
 * implementations cannot drift apart.
 *
 * Households are produced via the aggregate's public API and handed to
 * the concrete test through {@see self::seedHouseholds()}; the seeding
 * mechanism (in-memory array vs Households write-side repository) is
 * the concrete subclass's responsibility.
 */
trait MemberReadModelContractCases
{
    private const HOUSEHOLD_A         = '019571bf-5d51-7000-b500-00000000aa01';
    private const HOUSEHOLD_B         = '019571bf-5d51-7000-b500-00000000bb01';

    private const A_PRIMARY_ID        = '019571bf-5d51-7000-b500-00000000aa02';
    private const A_PRIMARY_CODE      = 'M000010';
    private const A_SECOND_ID         = '019571bf-5d51-7000-b500-00000000aa03';
    private const A_SECOND_CODE       = 'M000011';
    private const A_THIRD_ID          = '019571bf-5d51-7000-b500-00000000aa04';
    private const A_THIRD_CODE        = 'M000012';

    private const B_PRIMARY_ID        = '019571bf-5d51-7000-b500-00000000bb02';
    private const B_PRIMARY_CODE      = 'M000020';
    private const B_SECOND_ID         = '019571bf-5d51-7000-b500-00000000bb03';
    private const B_SECOND_CODE       = 'M000021';

    abstract protected function readModel(): MemberReadModel;

    /**
     * @param list<Household> $households
     */
    abstract protected function seedHouseholds(array $households): void;

    abstract protected function clock(): MockClock;

    #[Test]
    #[TestDox('search(): empty filters returns every active member across households, sorted by lastName, firstName.')]
    public function search_with_empty_filters_returns_all_members(): void
    {
        $this->seedHouseholds([
            $this->buildHouseholdA(),
            $this->buildHouseholdB(),
        ]);

        $page = $this->readModel()->search(new SearchMembersCriteria());

        self::assertSame(5, $page->totalItems);
        self::assertCount(5, $page->items);

        $codes = array_map(static fn($item): string => $item->memberCode, $page->items);
        self::assertSame(
            [
                self::A_SECOND_CODE,   // Bob Brown
                self::B_SECOND_CODE,   // Diana Lopez
                self::A_PRIMARY_CODE,  // Alice Smith
                self::B_PRIMARY_CODE,  // Carl Smith
                self::A_THIRD_CODE,    // Eli Underwood
            ],
            $codes,
        );
    }

    #[Test]
    #[TestDox('search(): memberCode filter returns the single matching row.')]
    public function search_by_member_code_returns_matching_row(): void
    {
        $this->seedHouseholds([
            $this->buildHouseholdA(),
            $this->buildHouseholdB(),
        ]);

        $page = $this->readModel()->search(new SearchMembersCriteria(
            memberCode: self::B_PRIMARY_CODE,
        ));

        self::assertSame(1, $page->totalItems);
        self::assertCount(1, $page->items);
        self::assertSame(self::B_PRIMARY_CODE, $page->items[0]->memberCode);
        self::assertSame('Carl Smith', $page->items[0]->fullName);
    }

    #[Test]
    #[TestDox('search(): lastName filter is a case-insensitive substring match.')]
    public function search_by_last_name_is_case_insensitive_substring(): void
    {
        $this->seedHouseholds([
            $this->buildHouseholdA(),
            $this->buildHouseholdB(),
        ]);

        $page = $this->readModel()->search(new SearchMembersCriteria(
            lastName: 'smi',
        ));

        self::assertSame(2, $page->totalItems);
        $codes = array_map(static fn($item): string => $item->memberCode, $page->items);
        sort($codes);
        self::assertSame([self::A_PRIMARY_CODE, self::B_PRIMARY_CODE], $codes);
    }

    #[Test]
    #[TestDox('search(): primaryOnly = true filters out secondary members.')]
    public function search_primary_only_filters_out_secondary_members(): void
    {
        $this->seedHouseholds([
            $this->buildHouseholdA(),
            $this->buildHouseholdB(),
        ]);

        $page = $this->readModel()->search(new SearchMembersCriteria(
            primaryOnly: true,
        ));

        self::assertSame(2, $page->totalItems);
        foreach ($page->items as $item) {
            self::assertTrue($item->isPrimary, sprintf('Expected %s to be primary.', $item->memberCode));
        }
    }

    #[Test]
    #[TestDox('search(): includeDeleted = false (default) hides deactivated members.')]
    public function search_excludes_deactivated_members_by_default(): void
    {
        $household = $this->buildHouseholdA();
        $household->deactivateMember(
            MemberId::fromString(self::A_THIRD_ID),
            'left the household',
            $this->clock(),
        );
        $this->seedHouseholds([$household]);

        $page = $this->readModel()->search(new SearchMembersCriteria());

        self::assertSame(2, $page->totalItems);
        $codes = array_map(static fn($item): string => $item->memberCode, $page->items);
        self::assertNotContains(self::A_THIRD_CODE, $codes);
    }

    #[Test]
    #[TestDox('search(): pagination respects page + pageSize and returns the requested slice.')]
    public function search_paginates_results(): void
    {
        $this->seedHouseholds([
            $this->buildHouseholdA(),  // 3 members
            $this->buildHouseholdB(),  // 2 members
        ]);

        $page = $this->readModel()->search(new SearchMembersCriteria(
            page: 2,
            pageSize: 2,
        ));

        self::assertSame(5, $page->totalItems);
        self::assertSame(3, $page->totalPages());
        self::assertCount(2, $page->items);

        // Sorted by lastName ASC, firstName ASC across both households:
        //   1. Bob Brown    (A_SECOND)
        //   2. Diana Lopez  (B_SECOND)
        //   3. Alice Smith  (A_PRIMARY)
        //   4. Carl Smith   (B_PRIMARY)
        //   5. Eli Underwood (A_THIRD)
        // Page 2 with pageSize 2 -> items 3-4.
        $codes = array_map(static fn($item): string => $item->memberCode, $page->items);
        self::assertSame([self::A_PRIMARY_CODE, self::B_PRIMARY_CODE], $codes);
    }

    #[Test]
    #[TestDox('memberDetail(): returns the composite DTO with household summary, profile, address, and residency.')]
    public function member_detail_returns_composite_dto(): void
    {
        $this->seedHouseholds([
            $this->buildHouseholdA(),
            $this->buildHouseholdB(),
        ]);

        $detail = $this->readModel()->memberDetail(
            HouseholdId::fromString(self::HOUSEHOLD_A),
            MemberId::fromString(self::A_PRIMARY_ID),
        );

        self::assertSame(self::HOUSEHOLD_A, $detail->household->householdId);
        self::assertSame('Smith Family', $detail->household->householdName);
        self::assertSame(3, $detail->household->memberCount);
        self::assertSame(self::A_PRIMARY_ID, $detail->household->primaryMemberId);
        self::assertSame('Alice Smith', $detail->household->primaryMemberFullName);

        self::assertSame(self::A_PRIMARY_ID, $detail->profile->memberId);
        self::assertSame(self::A_PRIMARY_CODE, $detail->profile->memberCode);
        self::assertSame('Alice', $detail->profile->firstName);
        self::assertSame('Smith', $detail->profile->lastName);
        self::assertSame('Alice Smith', $detail->profile->fullName);
        self::assertSame('1990-01-01', $detail->profile->dobIso);
        self::assertSame('F', $detail->profile->genderCode);
        self::assertSame('alice@example.com', $detail->profile->email);
        self::assertNull($detail->profile->phone);
        self::assertTrue($detail->profile->isPrimary);
        self::assertTrue($detail->profile->isActive);

        self::assertSame('100 Main St', $detail->address->street);
        self::assertSame('Apt 2B', $detail->address->unit);
        self::assertSame('Seattle', $detail->address->city);
        self::assertSame('WA', $detail->address->state);
        self::assertSame('98101', $detail->address->postalCode);
        self::assertSame('US', $detail->address->country);

        self::assertSame(ResidencyStatus::Resident->value, $detail->residency->status);
    }

    #[Test]
    #[TestDox('memberDetail(): throws MemberNotFound when the member id is unknown in the household.')]
    public function member_detail_throws_for_unknown_member(): void
    {
        $this->seedHouseholds([$this->buildHouseholdA()]);

        $this->expectException(MemberNotFound::class);

        $this->readModel()->memberDetail(
            HouseholdId::fromString(self::HOUSEHOLD_A),
            MemberId::fromString('019571bf-5d51-7000-b500-0000000000ff'),
        );
    }

    private function buildHouseholdA(): Household
    {
        $household = Household::register(
            HouseholdId::fromString(self::HOUSEHOLD_A),
            HouseholdName::of('Smith Family'),
            Address::of('100 Main St', 'Apt 2B', 'Seattle', 'WA', '98101', 'US'),
            MemberId::fromString(self::A_PRIMARY_ID),
            MemberCode::of(self::A_PRIMARY_CODE),
            PersonName::of('Alice', 'Smith'),
            DateOfBirth::of(new DateTimeImmutable('1990-01-01'), $this->clock()),
            Gender::Female,
            EmailAddress::of('alice@example.com'),
            null,
            ResidencyStatus::Resident,
            $this->clock(),
        );

        $household->addMember(
            MemberId::fromString(self::A_SECOND_ID),
            MemberCode::of(self::A_SECOND_CODE),
            PersonName::of('Bob', 'Brown'),
            DateOfBirth::of(new DateTimeImmutable('1992-03-04'), $this->clock()),
            Gender::Male,
            null,
            PhoneNumber::of('5550002'),
            ResidencyStatus::NonResident,
            false,
            $this->clock(),
        );

        $household->addMember(
            MemberId::fromString(self::A_THIRD_ID),
            MemberCode::of(self::A_THIRD_CODE),
            PersonName::of('Eli', 'Underwood'),
            DateOfBirth::of(new DateTimeImmutable('2005-07-12'), $this->clock()),
            Gender::Other,
            null,
            null,
            ResidencyStatus::Resident,
            false,
            $this->clock(),
        );

        return $household;
    }

    private function buildHouseholdB(): Household
    {
        $household = Household::register(
            HouseholdId::fromString(self::HOUSEHOLD_B),
            HouseholdName::of('Smith-Lopez Household'),
            Address::of('200 Oak Ave', null, 'Portland', 'OR', '97201', 'US'),
            MemberId::fromString(self::B_PRIMARY_ID),
            MemberCode::of(self::B_PRIMARY_CODE),
            PersonName::of('Carl', 'Smith'),
            DateOfBirth::of(new DateTimeImmutable('1985-11-30'), $this->clock()),
            Gender::Male,
            EmailAddress::of('carl@example.com'),
            PhoneNumber::of('5550100'),
            ResidencyStatus::Member,
            $this->clock(),
        );

        $household->addMember(
            MemberId::fromString(self::B_SECOND_ID),
            MemberCode::of(self::B_SECOND_CODE),
            PersonName::of('Diana', 'Lopez'),
            DateOfBirth::of(new DateTimeImmutable('1987-02-15'), $this->clock()),
            Gender::Female,
            EmailAddress::of('diana@example.com'),
            PhoneNumber::of('5550101'),
            ResidencyStatus::Member,
            false,
            $this->clock(),
        );

        return $household;
    }
}
