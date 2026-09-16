<?php

declare(strict_types=1);

namespace App\Tests\Integration\Households\Event;

use App\Households\Domain\Event\MemberMergedInto;
use App\Households\Domain\Household;
use App\Households\Domain\Households;
use App\Households\Domain\ValueObject\Address;
use App\Households\Domain\ValueObject\DateOfBirth;
use App\Households\Domain\ValueObject\Gender;
use App\Households\Domain\ValueObject\HouseholdId;
use App\Households\Domain\ValueObject\HouseholdName;
use App\Households\Domain\ValueObject\MemberCode;
use App\Households\Domain\ValueObject\MemberContact;
use App\Households\Domain\ValueObject\MemberId;
use App\Households\Domain\ValueObject\MemberProfile;
use App\Households\Domain\ValueObject\ResidencyStatus;
use App\Households\Domain\ValueObject\PersonName;
use App\Households\Infrastructure\Persistence\Doctrine\Event\RecordMemberMergeLineageHandler;
use App\Shared\Domain\ValueObject\EmailAddress;
use App\Shared\Domain\ValueObject\PhoneNumber;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;

/**
 * Direct integration test for the {@see RecordMemberMergeLineageHandler}
 * Messenger handler (LRA-208). Mirrors
 * {@see \App\Tests\Integration\Households\Event\RecordResidencyChangeHandlerTest}:
 * verifies a dispatched {@see MemberMergedInto} appends one MERGED_INTO
 * row to the shared `household_member_lineage` audit table.
 *
 * DAMA's transaction rollback isolates rows between cases.
 */
#[Medium]
#[Group('database')]
final class RecordMemberMergeLineageHandlerTest extends KernelTestCase
{
    private const string OCCURRED_AT = '2026-05-24 12:00:00';

    private const string DUPLICATE_HOUSEHOLD_ID = '019571bf-5d55-7000-b500-00000000cc01';
    private const string DUPLICATE_MEMBER_ID    = '019571bf-5d55-7000-b500-00000000cc02';
    private const string DUPLICATE_MEMBER_CODE  = 'M000520';
    private const string SURVIVOR_HOUSEHOLD_ID  = '019571bf-5d55-7000-b500-00000000cc03';
    private const string SURVIVOR_MEMBER_ID     = '019571bf-5d55-7000-b500-00000000cc04';
    private const string SURVIVOR_MEMBER_CODE   = 'M000521';

    private MockClock $clock;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->clock = new MockClock(new DateTimeImmutable(self::OCCURRED_AT));
        $this->seedHouseholds();
    }

    #[Test]
    #[TestDox('Appends one MERGED_INTO row to household_member_lineage for the dispatched event.')]
    public function appends_merged_into_row(): void
    {
        $handler = $this->handler();

        $handler(new MemberMergedInto(
            HouseholdId::fromString(self::DUPLICATE_HOUSEHOLD_ID),
            MemberId::fromString(self::DUPLICATE_MEMBER_ID),
            HouseholdId::fromString(self::SURVIVOR_HOUSEHOLD_ID),
            MemberId::fromString(self::SURVIVOR_MEMBER_ID),
            EmailAddress::of('dup@example.com'),
            PhoneNumber::of('5559999'),
            new DateTimeImmutable(self::OCCURRED_AT),
        ));

        $rows = $this->connection()->fetchAllAssociative(
            'SELECT household_id, member_id, related_household_id, related_member_id, kind, reason, transaction_ids '
            . 'FROM household_member_lineage WHERE member_id = :m ORDER BY id ASC',
            ['m' => self::DUPLICATE_MEMBER_ID],
        );

        self::assertCount(1, $rows);
        self::assertSame(self::DUPLICATE_HOUSEHOLD_ID, $rows[0]['household_id']);
        self::assertSame(self::DUPLICATE_MEMBER_ID, $rows[0]['member_id']);
        self::assertSame(self::SURVIVOR_HOUSEHOLD_ID, $rows[0]['related_household_id']);
        self::assertSame(self::SURVIVOR_MEMBER_ID, $rows[0]['related_member_id']);
        self::assertSame('MERGED_INTO', $rows[0]['kind']);
        self::assertNull($rows[0]['reason']);
        self::assertNull($rows[0]['transaction_ids']);
    }

    private function handler(): RecordMemberMergeLineageHandler
    {
        $handler = static::getContainer()->get(RecordMemberMergeLineageHandler::class);
        self::assertInstanceOf(RecordMemberMergeLineageHandler::class, $handler);

        return $handler;
    }

    private function connection(): Connection
    {
        $connection = static::getContainer()->get(Connection::class);
        self::assertInstanceOf(Connection::class, $connection);

        return $connection;
    }

    private function seedHouseholds(): void
    {
        $repo = static::getContainer()->get(Households::class);
        self::assertInstanceOf(Households::class, $repo);

        $duplicate = Household::register(
            HouseholdId::fromString(self::DUPLICATE_HOUSEHOLD_ID),
            HouseholdName::of('Lineage Duplicate Family'),
            Address::of('1 Test St', null, 'Seattle', 'WA', '98101', 'US'),
            MemberId::fromString(self::DUPLICATE_MEMBER_ID),
            MemberCode::of(self::DUPLICATE_MEMBER_CODE),
            MemberProfile::of(
                PersonName::of('Dup', 'Test'),
                DateOfBirth::of(new DateTimeImmutable('1990-01-01'), $this->clock),
                Gender::Unspecified,
            ),
            MemberContact::of(EmailAddress::of('dup@example.com'), null),
            ResidencyStatus::Resident,
            $this->clock,
        );
        $repo->save($duplicate);

        $survivor = Household::register(
            HouseholdId::fromString(self::SURVIVOR_HOUSEHOLD_ID),
            HouseholdName::of('Lineage Survivor Family'),
            Address::of('2 Test St', null, 'Seattle', 'WA', '98101', 'US'),
            MemberId::fromString(self::SURVIVOR_MEMBER_ID),
            MemberCode::of(self::SURVIVOR_MEMBER_CODE),
            MemberProfile::of(
                PersonName::of('Surv', 'Test'),
                DateOfBirth::of(new DateTimeImmutable('1990-01-01'), $this->clock),
                Gender::Unspecified,
            ),
            MemberContact::of(null, null),
            ResidencyStatus::Resident,
            $this->clock,
        );
        $repo->save($survivor);
    }
}
