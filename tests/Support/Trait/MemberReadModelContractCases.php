<?php

declare(strict_types=1);

namespace App\Tests\Support\Trait;

use App\Households\Application\Query\Port\MemberReadModel;
use App\Households\Application\Query\Port\MembersSegment;
use App\Households\Application\Query\Port\SearchMembersCriteria;
use App\Households\Domain\Exception\MemberNotFound;
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
use App\Households\Domain\ValueObject\Weight;
use DateTimeImmutable;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
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

    private const A_MINOR_ID          = '019571bf-5d51-7000-b500-00000000aa05';
    private const A_MINOR_CODE        = 'M000013';

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
    #[TestDox('search(): includeMerged = false (default) hides merged members.')]
    public function search_excludes_merged_members_by_default(): void
    {
        $household = $this->buildHouseholdA();
        $household->mergeMemberInto(
            MemberId::fromString(self::A_SECOND_ID),
            HouseholdId::fromString(self::HOUSEHOLD_A),
            MemberId::fromString(self::A_PRIMARY_ID),
            $this->clock(),
        );
        $this->seedHouseholds([$household]);

        $page = $this->readModel()->search(new SearchMembersCriteria());

        self::assertSame(2, $page->totalItems);
        $codes = array_map(static fn($item): string => $item->memberCode, $page->items);
        self::assertNotContains(self::A_SECOND_CODE, $codes);
    }

    #[Test]
    #[TestDox('search(): includeMerged = true includes merged members, flagged isMerged.')]
    public function search_includes_merged_members_when_requested(): void
    {
        $household = $this->buildHouseholdA();
        $household->mergeMemberInto(
            MemberId::fromString(self::A_SECOND_ID),
            HouseholdId::fromString(self::HOUSEHOLD_A),
            MemberId::fromString(self::A_PRIMARY_ID),
            $this->clock(),
        );
        $this->seedHouseholds([$household]);

        $page = $this->readModel()->search(new SearchMembersCriteria(includeMerged: true));

        self::assertSame(3, $page->totalItems);
        $merged = null;
        foreach ($page->items as $item) {
            if ($item->memberCode === self::A_SECOND_CODE) {
                $merged = $item;
                break;
            }
        }
        self::assertNotNull($merged);
        self::assertTrue($merged->isMerged);
    }

    #[Test]
    #[TestDox('segmentCounts(): ignores merged members.')]
    public function segment_counts_excludes_merged_members(): void
    {
        $household = $this->buildHouseholdA();
        $household->mergeMemberInto(
            MemberId::fromString(self::A_SECOND_ID),
            HouseholdId::fromString(self::HOUSEHOLD_A),
            MemberId::fromString(self::A_PRIMARY_ID),
            $this->clock(),
        );
        $this->seedHouseholds([$household]);

        $counts = $this->readModel()->segmentCounts(null);

        // Alice + Eli are active and unmerged; Bob is merged and excluded.
        self::assertSame(2, $counts->all);
    }

    #[Test]
    #[TestDox('memberDetail(): projects the merge pointer for a merged member.')]
    public function member_detail_projects_merge_pointer_for_merged_member(): void
    {
        $household = $this->buildHouseholdA();
        $household->mergeMemberInto(
            MemberId::fromString(self::A_SECOND_ID),
            HouseholdId::fromString(self::HOUSEHOLD_A),
            MemberId::fromString(self::A_PRIMARY_ID),
            $this->clock(),
        );
        $this->seedHouseholds([$household]);

        $detail = $this->readModel()->memberDetail(
            HouseholdId::fromString(self::HOUSEHOLD_A),
            MemberId::fromString(self::A_SECOND_ID),
        );

        self::assertSame(self::A_PRIMARY_ID, $detail->profile->mergedIntoMemberId);
        self::assertSame(self::HOUSEHOLD_A, $detail->profile->mergedIntoHouseholdId);
        self::assertNotNull($detail->profile->mergedAtIso);

        // The merged member still appears in the household roster, flagged.
        $rosterEntry = null;
        foreach ($detail->householdMembers as $item) {
            if ($item->memberId === self::A_SECOND_ID) {
                $rosterEntry = $item;
                break;
            }
        }
        self::assertNotNull($rosterEntry);
        self::assertTrue($rosterEntry->isMerged);
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

    /**
     * @return Generator<string, array{q: string, expectedCodes: list<string>}>
     */
    public static function qMatchCases(): Generator
    {
        yield 'matches last name' => ['q' => 'brown', 'expectedCodes' => [self::A_SECOND_CODE]];
        yield 'matches first name' => ['q' => 'diana', 'expectedCodes' => [self::B_SECOND_CODE]];
        yield 'matches member code' => ['q' => self::A_THIRD_CODE, 'expectedCodes' => [self::A_THIRD_CODE]];
        // Bob's phone is '5550002'; a shorter '0002' substring would also
        // accidentally match member codes M000020/M000021 (Household B).
        yield 'matches phone' => ['q' => '5550002', 'expectedCodes' => [self::A_SECOND_CODE]];
        yield 'matches household name' => [
            'q' => 'lopez',
            'expectedCodes' => [self::B_PRIMARY_CODE, self::B_SECOND_CODE],
        ];
    }

    /**
     * @param list<string> $expectedCodes
     */
    #[Test]
    #[DataProvider('qMatchCases')]
    #[TestDox('search(): q matches last/first name, member code, phone, or household name (OR, case-insensitive).')]
    public function search_with_q_matches_across_multiple_fields(string $q, array $expectedCodes): void
    {
        $this->seedHouseholds([
            $this->buildHouseholdA(),
            $this->buildHouseholdB(),
        ]);

        $page = $this->readModel()->search(new SearchMembersCriteria(q: $q));

        $codes = array_map(static fn($item): string => $item->memberCode, $page->items);
        sort($codes);
        sort($expectedCodes);
        self::assertSame($expectedCodes, $codes);
    }

    #[Test]
    #[TestDox('search(): q does not match email — the detailed email filter covers that.')]
    public function search_with_q_does_not_match_email(): void
    {
        $this->seedHouseholds([$this->buildHouseholdA()]);

        $page = $this->readModel()->search(new SearchMembersCriteria(q: 'alice@example.com'));

        self::assertSame(0, $page->totalItems);
    }

    #[Test]
    #[TestDox('search(): q treats "_" as a literal character, not a LIKE wildcard matching everything.')]
    public function search_with_q_underscore_does_not_match_every_row(): void
    {
        $this->seedHouseholds([$this->buildHouseholdA(), $this->buildHouseholdB()]);

        $page = $this->readModel()->search(new SearchMembersCriteria(q: '_'));

        self::assertSame(0, $page->totalItems);
    }

    #[Test]
    #[TestDox('search(): q matches non-ASCII names case-insensitively regardless of accent casing.')]
    public function search_with_q_matches_non_ascii_case_insensitively(): void
    {
        $household = Household::register(
            HouseholdId::fromString('019571bf-5d51-7000-b500-0000000000cd'),
            HouseholdName::of('Muller Household'),
            Address::of('1 Elm St', null, 'Berlin', 'BE', '10115', 'DE'),
            MemberId::fromString('019571bf-5d51-7000-b500-0000000000ce'),
            MemberCode::of('M000090'),
            MemberProfile::of(
                PersonName::of('Uwe', 'Müller'),
                DateOfBirth::of(new DateTimeImmutable('1980-04-04'), $this->clock()),
                Gender::Male,
            ),
            MemberContact::of(EmailAddress::of('uwe@example.com'), null),
            ResidencyStatus::Resident,
            $this->clock(),
        );
        $this->seedHouseholds([$household]);

        $page = $this->readModel()->search(new SearchMembersCriteria(q: 'MÜLLER'));

        self::assertSame(1, $page->totalItems);
        self::assertSame('M000090', $page->items[0]->memberCode);
    }

    #[Test]
    #[TestDox('search(): segment=Residents returns only active members with Resident residency.')]
    public function search_with_segment_residents_returns_only_residents(): void
    {
        $this->seedHouseholds([$this->buildHouseholdA(), $this->buildHouseholdB()]);

        $page = $this->readModel()->search(new SearchMembersCriteria(segment: MembersSegment::Residents));

        $codes = array_map(static fn($item): string => $item->memberCode, $page->items);
        sort($codes);
        self::assertSame([self::A_PRIMARY_CODE, self::A_THIRD_CODE], $codes);
    }

    #[Test]
    #[TestDox('search(): segment=NonResidents returns only active members with NonResident residency.')]
    public function search_with_segment_non_residents_returns_only_non_residents(): void
    {
        $this->seedHouseholds([$this->buildHouseholdA(), $this->buildHouseholdB()]);

        $page = $this->readModel()->search(new SearchMembersCriteria(segment: MembersSegment::NonResidents));

        self::assertSame(
            [self::A_SECOND_CODE],
            array_map(static fn($item): string => $item->memberCode, $page->items),
        );
    }

    #[Test]
    #[TestDox('search(): segment=Inactive returns deactivated members even though includeDeleted defaults to false.')]
    public function search_with_segment_inactive_returns_deactivated_members(): void
    {
        $household = $this->buildHouseholdA();
        $household->deactivateMember(MemberId::fromString(self::A_THIRD_ID), 'left the household', $this->clock());
        $this->seedHouseholds([$household]);

        $page = $this->readModel()->search(new SearchMembersCriteria(segment: MembersSegment::Inactive));

        self::assertSame(
            [self::A_THIRD_CODE],
            array_map(static fn($item): string => $item->memberCode, $page->items),
        );
    }

    #[Test]
    #[TestDox('segmentCounts(): counts active/resident/non-resident/inactive members with no q filter.')]
    public function segment_counts_reflects_current_state(): void
    {
        $household = $this->buildHouseholdA();
        $household->deactivateMember(MemberId::fromString(self::A_THIRD_ID), 'left the household', $this->clock());
        $this->seedHouseholds([$household, $this->buildHouseholdB()]);

        $counts = $this->readModel()->segmentCounts(null);

        // Active: Alice (Resident), Bob (NonResident), Carl (Member), Diana (Member). Eli is deactivated.
        self::assertSame(4, $counts->all);
        self::assertSame(1, $counts->residents);
        self::assertSame(1, $counts->nonResidents);
        self::assertSame(1, $counts->inactive);
    }

    #[Test]
    #[TestDox('segmentCounts(): narrows every segment to members matching q first.')]
    public function segment_counts_narrows_by_q(): void
    {
        $this->seedHouseholds([$this->buildHouseholdA(), $this->buildHouseholdB()]);

        $counts = $this->readModel()->segmentCounts('brown');

        self::assertSame(1, $counts->all);
        self::assertSame(0, $counts->residents);
        self::assertSame(1, $counts->nonResidents);
        self::assertSame(0, $counts->inactive);
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
        self::assertNull($detail->profile->nickname);
        self::assertNull($detail->profile->salutationCode);
        self::assertNull($detail->profile->heightInches);
        self::assertNull($detail->profile->weightPounds);
        self::assertTrue($detail->profile->isPrimary);
        self::assertTrue($detail->profile->isActive);

        self::assertSame('100 Main St', $detail->address->street);
        self::assertSame('Apt 2B', $detail->address->unit);
        self::assertSame('Seattle', $detail->address->city);
        self::assertSame('WA', $detail->address->state);
        self::assertSame('98101', $detail->address->postalCode);
        self::assertSame('US', $detail->address->country);

        self::assertSame(ResidencyStatus::Resident->value, $detail->residency->status);

        // Household roster (LRA-42): every member of Household A appears,
        // sorted by lastName / firstName / id ASC to match the list page.
        self::assertCount(3, $detail->householdMembers);
        $codes = array_map(
            static fn($item): string => $item->memberCode,
            $detail->householdMembers,
        );
        self::assertSame(
            [
                self::A_SECOND_CODE,   // Bob Brown
                self::A_PRIMARY_CODE,  // Alice Smith
                self::A_THIRD_CODE,    // Eli Underwood
            ],
            $codes,
        );
        // Active member is included in the roster.
        $rosterIds = array_map(
            static fn($item): string => $item->memberId,
            $detail->householdMembers,
        );
        self::assertContains(self::A_PRIMARY_ID, $rosterIds);
        // Primary flag is preserved per-row.
        foreach ($detail->householdMembers as $item) {
            self::assertSame(
                $item->memberId === self::A_PRIMARY_ID,
                $item->isPrimary,
                sprintf('Expected isPrimary mismatch for member %s.', $item->memberId),
            );
        }
    }

    #[Test]
    #[TestDox('memberDetail(): householdMembers contains deactivated members so the Household card can dim them.')]
    public function member_detail_household_members_includes_deactivated(): void
    {
        $household = $this->buildHouseholdA();
        $household->deactivateMember(
            MemberId::fromString(self::A_THIRD_ID),
            'left the household',
            $this->clock(),
        );
        $this->seedHouseholds([$household]);

        $detail = $this->readModel()->memberDetail(
            HouseholdId::fromString(self::HOUSEHOLD_A),
            MemberId::fromString(self::A_PRIMARY_ID),
        );

        self::assertCount(3, $detail->householdMembers);
        $deactivated = null;
        foreach ($detail->householdMembers as $item) {
            if ($item->memberId === self::A_THIRD_ID) {
                $deactivated = $item;
                break;
            }
        }
        self::assertNotNull($deactivated, 'Deactivated member should still appear in the household roster.');
        self::assertFalse($deactivated->isActive);
    }

    #[Test]
    #[TestDox('memberDetail(): exposes anonymizedAtIso on the profile and isAnonymized on the household roster.')]
    public function member_detail_exposes_anonymized_at_and_placeholder_profile(): void
    {
        $household = $this->buildHouseholdA();
        $household->anonymizeMember(
            MemberId::fromString(self::A_THIRD_ID),
            AnonymizedProfile::placeholder(),
            $this->clock(),
        );
        $this->seedHouseholds([$household]);

        $anonymizedDetail = $this->readModel()->memberDetail(
            HouseholdId::fromString(self::HOUSEHOLD_A),
            MemberId::fromString(self::A_THIRD_ID),
        );
        self::assertNotNull($anonymizedDetail->profile->anonymizedAtIso);
        self::assertFalse($anonymizedDetail->profile->isActive);
        // The read model must actually project the placeholder values —
        // not merely stop erroring — and the member code must survive
        // untouched so downstream references keep resolving.
        self::assertSame(self::A_THIRD_CODE, $anonymizedDetail->profile->memberCode);
        self::assertSame('Anonymized', $anonymizedDetail->profile->firstName);
        self::assertSame('Member', $anonymizedDetail->profile->lastName);
        self::assertSame('1900-01-01', substr((string) $anonymizedDetail->profile->dobIso, 0, 10));
        self::assertSame(Gender::Unspecified->value, $anonymizedDetail->profile->genderCode);

        $primaryDetail = $this->readModel()->memberDetail(
            HouseholdId::fromString(self::HOUSEHOLD_A),
            MemberId::fromString(self::A_PRIMARY_ID),
        );
        self::assertNull($primaryDetail->profile->anonymizedAtIso);

        $anonymizedRosterItem = null;
        foreach ($primaryDetail->householdMembers as $item) {
            if ($item->memberId === self::A_THIRD_ID) {
                $anonymizedRosterItem = $item;
                break;
            }
        }
        self::assertNotNull($anonymizedRosterItem, 'Anonymized member should still appear in the household roster.');
        self::assertTrue($anonymizedRosterItem->isAnonymized);
    }

    #[Test]
    #[TestDox('memberDetail(): projects nickname, salutation, height, and weight when populated.')]
    public function member_detail_projects_measurement_fields_when_populated(): void
    {
        $household = $this->buildHouseholdA(
            nickname: 'Al',
            salutation: Salutation::Ms,
            height: Height::ofInches(65),
            weight: Weight::ofPounds(140),
        );
        $this->seedHouseholds([$household]);

        $detail = $this->readModel()->memberDetail(
            HouseholdId::fromString(self::HOUSEHOLD_A),
            MemberId::fromString(self::A_PRIMARY_ID),
        );

        self::assertSame('Al', $detail->profile->nickname);
        self::assertSame('MS', $detail->profile->salutationCode);
        self::assertSame(65, $detail->profile->heightInches);
        self::assertSame(140, $detail->profile->weightPounds);
    }

    #[Test]
    #[TestDox('memberDetail(): exposes photoVersion + photoMimeType on the profile and photoVersion on roster items.')]
    public function member_detail_exposes_photo_version(): void
    {
        $household = $this->buildHouseholdA();
        $photo = ProfilePhoto::of(
            self::A_PRIMARY_ID . '/bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb.png',
            ImageFormat::Png,
            $this->clock()->now(),
        );
        $household->attachMemberPhoto(MemberId::fromString(self::A_PRIMARY_ID), $photo, $this->clock());
        $this->seedHouseholds([$household]);

        $detail = $this->readModel()->memberDetail(
            HouseholdId::fromString(self::HOUSEHOLD_A),
            MemberId::fromString(self::A_PRIMARY_ID),
        );

        self::assertSame($photo->version(), $detail->profile->photoVersion);
        self::assertSame('image/png', $detail->profile->photoMimeType);

        $primaryRosterItem = null;
        $secondRosterItem = null;
        foreach ($detail->householdMembers as $item) {
            if ($item->memberId === self::A_PRIMARY_ID) {
                $primaryRosterItem = $item;
            }
            if ($item->memberId === self::A_SECOND_ID) {
                $secondRosterItem = $item;
            }
        }

        self::assertNotNull($primaryRosterItem);
        self::assertSame($photo->version(), $primaryRosterItem->photoVersion);
        self::assertNotNull($secondRosterItem);
        self::assertNull($secondRosterItem->photoVersion);
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

    #[Test]
    #[TestDox('memberDetail(): a shared minor viewed via a link scopes household/roster, exposes homeHouseholdId.')]
    public function member_detail_for_shared_minor_viewed_from_linked_household(): void
    {
        $home = $this->buildHouseholdA();
        $this->addMinorTo($home);
        $home->shareMemberWithHousehold(
            MemberId::fromString(self::A_MINOR_ID),
            HouseholdId::fromString(self::HOUSEHOLD_B),
            $this->clock(),
        );
        // Household B must exist before $home is saved: the affiliation row
        // saved with $home carries a foreign key to household B.
        $this->seedHouseholds([$this->buildHouseholdB(), $home]);

        $detail = $this->readModel()->memberDetail(
            HouseholdId::fromString(self::HOUSEHOLD_B),
            MemberId::fromString(self::A_MINOR_ID),
        );

        // Household summary + address reflect the VIEWED household (B),
        // not home (A) — B's own 2 members plus the 1 shared-in minor.
        self::assertSame(self::HOUSEHOLD_B, $detail->household->householdId);
        self::assertSame('Smith-Lopez Household', $detail->household->householdName);
        self::assertSame(3, $detail->household->memberCount);
        self::assertSame('200 Oak Ave', $detail->address->street);

        // Identity/profile is still the minor's own (home) data.
        self::assertSame('Fiona', $detail->profile->firstName);

        // homeHouseholdId names the actual owner, regardless of viewed household.
        self::assertSame(self::HOUSEHOLD_A, $detail->homeHouseholdId);

        // linkedHouseholds: home first (no linkedAt), then the shared household.
        self::assertCount(2, $detail->linkedHouseholds);
        self::assertSame(self::HOUSEHOLD_A, $detail->linkedHouseholds[0]->householdId);
        self::assertTrue($detail->linkedHouseholds[0]->isHome);
        self::assertNull($detail->linkedHouseholds[0]->linkedAtIso);
        self::assertSame(self::HOUSEHOLD_B, $detail->linkedHouseholds[1]->householdId);
        self::assertFalse($detail->linkedHouseholds[1]->isHome);
        self::assertNotNull($detail->linkedHouseholds[1]->linkedAtIso);

        // Roster of the viewed household (B) includes the shared minor,
        // flagged isShared and attributed to their home household; B's own
        // members are not flagged.
        $sharedRow = null;
        foreach ($detail->householdMembers as $item) {
            if ($item->memberId === self::A_MINOR_ID) {
                $sharedRow = $item;

                continue;
            }
            self::assertFalse($item->isShared, sprintf('Expected %s not flagged shared.', $item->memberId));
        }
        self::assertNotNull($sharedRow);
        self::assertTrue($sharedRow->isShared);
        self::assertSame(self::HOUSEHOLD_A, $sharedRow->householdId);
    }

    #[Test]
    #[TestDox('memberDetail(): throws MemberNotFound when viewed from an unrelated household.')]
    public function member_detail_throws_for_unrelated_household(): void
    {
        $home = $this->buildHouseholdA();
        $this->addMinorTo($home);
        $this->seedHouseholds([$home, $this->buildHouseholdB()]);

        $this->expectException(MemberNotFound::class);

        $this->readModel()->memberDetail(
            HouseholdId::fromString(self::HOUSEHOLD_B),
            MemberId::fromString(self::A_MINOR_ID),
        );
    }

    private function addMinorTo(Household $household): void
    {
        $household->addMember(
            MemberId::fromString(self::A_MINOR_ID),
            MemberCode::of(self::A_MINOR_CODE),
            MemberProfile::of(
                PersonName::of('Fiona', 'Smith'),
                DateOfBirth::of(new DateTimeImmutable('2015-01-01'), $this->clock()),
                Gender::Female,
            ),
            MemberContact::none(),
            ResidencyStatus::Resident,
            false,
            $this->clock(),
        );
    }

    /**
     * @param non-empty-string|null $nickname Optional measurement/identity
     *        fields (LRA-205) for the primary member, "Al"ice Smith. Left
     *        null by every other call site so the projection stays
     *        unpopulated for existing assertions; only
     *        {@see self::member_detail_projects_measurement_fields_when_populated()}
     *        supplies them, avoiding a second near-duplicate `register()` call.
     */
    private function buildHouseholdA(
        ?string $nickname = null,
        ?Salutation $salutation = null,
        ?Height $height = null,
        ?Weight $weight = null,
    ): Household {
        $household = Household::register(
            HouseholdId::fromString(self::HOUSEHOLD_A),
            HouseholdName::of('Smith Family'),
            Address::of('100 Main St', 'Apt 2B', 'Seattle', 'WA', '98101', 'US'),
            MemberId::fromString(self::A_PRIMARY_ID),
            MemberCode::of(self::A_PRIMARY_CODE),
            MemberProfile::of(
                PersonName::of('Alice', 'Smith', nickname: $nickname),
                DateOfBirth::of(new DateTimeImmutable('1990-01-01'), $this->clock()),
                Gender::Female,
                $salutation,
                $height,
                $weight,
            ),
            MemberContact::of(EmailAddress::of('alice@example.com'), null),
            ResidencyStatus::Resident,
            $this->clock(),
        );

        $household->addMember(
            MemberId::fromString(self::A_SECOND_ID),
            MemberCode::of(self::A_SECOND_CODE),
            MemberProfile::of(
                PersonName::of('Bob', 'Brown'),
                DateOfBirth::of(new DateTimeImmutable('1992-03-04'), $this->clock()),
                Gender::Male,
            ),
            MemberContact::of(null, PhoneNumber::of('5550002')),
            ResidencyStatus::NonResident,
            false,
            $this->clock(),
        );

        $household->addMember(
            MemberId::fromString(self::A_THIRD_ID),
            MemberCode::of(self::A_THIRD_CODE),
            MemberProfile::of(
                PersonName::of('Eli', 'Underwood'),
                DateOfBirth::of(new DateTimeImmutable('2005-07-12'), $this->clock()),
                Gender::Other,
            ),
            MemberContact::none(),
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
            MemberProfile::of(
                PersonName::of('Carl', 'Smith'),
                DateOfBirth::of(new DateTimeImmutable('1985-11-30'), $this->clock()),
                Gender::Male,
            ),
            MemberContact::of(EmailAddress::of('carl@example.com'), PhoneNumber::of('5550100')),
            ResidencyStatus::Member,
            $this->clock(),
        );

        $household->addMember(
            MemberId::fromString(self::B_SECOND_ID),
            MemberCode::of(self::B_SECOND_CODE),
            MemberProfile::of(
                PersonName::of('Diana', 'Lopez'),
                DateOfBirth::of(new DateTimeImmutable('1987-02-15'), $this->clock()),
                Gender::Female,
            ),
            MemberContact::of(EmailAddress::of('diana@example.com'), PhoneNumber::of('5550101')),
            ResidencyStatus::Member,
            false,
            $this->clock(),
        );

        return $household;
    }
}
