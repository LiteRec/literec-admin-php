<?php

declare(strict_types=1);

namespace App\Tests\Integration\Households\Persistence;

use App\Households\Domain\Household;
use App\Households\Domain\Households;
use App\Households\Domain\MemberFreeTextReasons;
use App\Households\Domain\ValueObject\Address;
use App\Households\Domain\ValueObject\DateOfBirth;
use App\Households\Domain\ValueObject\Gender;
use App\Households\Domain\ValueObject\HouseholdId;
use App\Households\Domain\ValueObject\HouseholdName;
use App\Households\Domain\ValueObject\MemberCode;
use App\Households\Domain\ValueObject\MemberId;
use App\Households\Domain\ValueObject\MemberLineageKind;
use App\Households\Domain\ValueObject\PersonName;
use App\Households\Domain\ValueObject\ResidencyStatus;
use App\Shared\Domain\ValueObject\EmailAddress;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;

/**
 * Direct integration test for the
 * {@see \App\Households\Infrastructure\Persistence\Doctrine\DoctrineMemberFreeTextReasons}
 * adapter (LRA-212). Verifies that {@see MemberFreeTextReasons::scrubFor()}
 * nulls the free-text `reason` on every `household_residency_history` and
 * `household_member_lineage` row that names the member, on either side of
 * a lineage row, while leaving other members' rows untouched.
 *
 * DAMA's transaction rollback isolates rows between cases.
 */
#[Medium]
#[Group('database')]
final class DoctrineMemberFreeTextReasonsTest extends KernelTestCase
{
    private const string RECORDED_AT = '2026-05-24 12:00:00';
    private const string EFFECTIVE_FROM = '2026-05-01 00:00:00';

    private const string HOUSEHOLD_ID = '019571bf-5d55-7000-b500-00000000cc01';
    private const string ANONYMIZED_MEMBER_ID = '019571bf-5d55-7000-b500-00000000cc02';
    private const string OTHER_MEMBER_ID = '019571bf-5d55-7000-b500-00000000cc03';
    private const string SPLIT_SOURCE_MEMBER_ID = '019571bf-5d55-7000-b500-00000000cc04';
    private const string ANONYMIZED_MEMBER_CODE = 'M000520';
    private const string OTHER_MEMBER_CODE = 'M000521';

    private MockClock $clock;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->clock = new MockClock(new DateTimeImmutable(self::RECORDED_AT));
        $this->seedHousehold();
        $this->seedHistoryRow(self::ANONYMIZED_MEMBER_ID, 'moved in with her mother');
        $this->seedHistoryRow(self::OTHER_MEMBER_ID, 'started college');
        // The anonymized member is the *new* member on this row (member_id)
        // and self::SPLIT_SOURCE_MEMBER_ID is the row it was split from
        // (related_member_id) — the reason must be cleared even though the
        // anonymized member is not the row's related_member_id.
        $this->seedLineageRow(
            memberId: self::ANONYMIZED_MEMBER_ID,
            relatedMemberId: self::SPLIT_SOURCE_MEMBER_ID,
            reason: "ex-spouse moved out, split Jane's purchases",
        );
        // The other member's own (unrelated) lineage row must survive.
        $this->seedLineageRow(
            memberId: self::OTHER_MEMBER_ID,
            relatedMemberId: self::SPLIT_SOURCE_MEMBER_ID,
            reason: 'unrelated split',
        );
    }

    #[Test]
    #[TestDox('scrubFor(): nulls the member\'s history reason and leaves other members\' reasons untouched.')]
    public function scrub_for_nulls_only_the_members_history_reason(): void
    {
        $this->scrubber()->scrubFor(MemberId::fromString(self::ANONYMIZED_MEMBER_ID));

        $scrubbedReason = $this->connection()->fetchOne(
            'SELECT reason FROM household_residency_history WHERE member_id = :m',
            ['m' => self::ANONYMIZED_MEMBER_ID],
        );
        $otherReason = $this->connection()->fetchOne(
            'SELECT reason FROM household_residency_history WHERE member_id = :m',
            ['m' => self::OTHER_MEMBER_ID],
        );

        self::assertNull($scrubbedReason);
        self::assertSame('started college', $otherReason);
    }

    #[Test]
    #[TestDox('scrubFor(): nulls the member\'s lineage reason on either side of the row, leaves others untouched.')]
    public function scrub_for_nulls_only_the_members_lineage_reason(): void
    {
        $this->scrubber()->scrubFor(MemberId::fromString(self::ANONYMIZED_MEMBER_ID));

        $scrubbedReason = $this->connection()->fetchOne(
            'SELECT reason FROM household_member_lineage WHERE member_id = :m',
            ['m' => self::ANONYMIZED_MEMBER_ID],
        );
        $otherReason = $this->connection()->fetchOne(
            'SELECT reason FROM household_member_lineage WHERE member_id = :m',
            ['m' => self::OTHER_MEMBER_ID],
        );

        self::assertNull($scrubbedReason);
        self::assertSame('unrelated split', $otherReason);
    }

    private function scrubber(): MemberFreeTextReasons
    {
        $scrubber = static::getContainer()->get(MemberFreeTextReasons::class);
        self::assertInstanceOf(MemberFreeTextReasons::class, $scrubber);

        return $scrubber;
    }

    private function connection(): Connection
    {
        $connection = static::getContainer()->get(Connection::class);
        self::assertInstanceOf(Connection::class, $connection);

        return $connection;
    }

    private function seedHistoryRow(string $memberId, string $reason): void
    {
        $this->connection()->executeStatement(
            'INSERT INTO household_residency_history '
            . '(household_id, member_id, status, effective_from, reason, recorded_at) '
            . 'VALUES (:household_id, :member_id, :status, :effective_from, :reason, :recorded_at)',
            [
                'household_id'   => self::HOUSEHOLD_ID,
                'member_id'      => $memberId,
                'status'         => ResidencyStatus::Resident->value,
                'effective_from' => self::EFFECTIVE_FROM,
                'reason'         => $reason,
                'recorded_at'    => self::RECORDED_AT,
            ],
        );
    }

    private function seedLineageRow(string $memberId, string $relatedMemberId, string $reason): void
    {
        $this->connection()->executeStatement(
            'INSERT INTO household_member_lineage '
            . '(household_id, member_id, related_household_id, related_member_id, kind, reason, recorded_at) '
            . 'VALUES '
            . '(:household_id, :member_id, :related_household_id, :related_member_id, :kind, :reason, :recorded_at)',
            [
                'household_id'         => self::HOUSEHOLD_ID,
                'member_id'            => $memberId,
                'related_household_id' => self::HOUSEHOLD_ID,
                'related_member_id'    => $relatedMemberId,
                'kind'                 => MemberLineageKind::SplitFrom->value,
                'reason'               => $reason,
                'recorded_at'          => self::RECORDED_AT,
            ],
        );
    }

    private function seedHousehold(): void
    {
        $repo = static::getContainer()->get(Households::class);
        self::assertInstanceOf(Households::class, $repo);

        $household = Household::register(
            HouseholdId::fromString(self::HOUSEHOLD_ID),
            HouseholdName::of('Scrub Test Family'),
            Address::of('1 Test St', null, 'Seattle', 'WA', '98101', 'US'),
            MemberId::fromString(self::ANONYMIZED_MEMBER_ID),
            MemberCode::of(self::ANONYMIZED_MEMBER_CODE),
            PersonName::of('Anon', 'Candidate'),
            DateOfBirth::of(new DateTimeImmutable('1990-01-01'), $this->clock),
            Gender::Unspecified,
            EmailAddress::of('anon-candidate@example.com'),
            null,
            ResidencyStatus::Resident,
            $this->clock,
        );

        $household->addMember(
            MemberId::fromString(self::OTHER_MEMBER_ID),
            MemberCode::of(self::OTHER_MEMBER_CODE),
            PersonName::of('Other', 'Member'),
            DateOfBirth::of(new DateTimeImmutable('1992-01-01'), $this->clock),
            Gender::Unspecified,
            null,
            null,
            ResidencyStatus::Resident,
            false,
            $this->clock,
        );

        $repo->save($household);
    }
}
