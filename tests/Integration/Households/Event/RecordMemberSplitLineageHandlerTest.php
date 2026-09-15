<?php

declare(strict_types=1);

namespace App\Tests\Integration\Households\Event;

use App\Households\Domain\Event\MemberSplitOff;
use App\Households\Domain\Household;
use App\Households\Domain\Households;
use App\Households\Domain\ValueObject\Address;
use App\Households\Domain\ValueObject\DateOfBirth;
use App\Households\Domain\ValueObject\Gender;
use App\Households\Domain\ValueObject\HouseholdId;
use App\Households\Domain\ValueObject\HouseholdName;
use App\Households\Domain\ValueObject\MemberCode;
use App\Households\Domain\ValueObject\MemberId;
use App\Households\Domain\ValueObject\PersonName;
use App\Households\Domain\ValueObject\ResidencyStatus;
use App\Households\Domain\ValueObject\TransactionReferences;
use App\Households\Infrastructure\Persistence\Doctrine\Event\RecordMemberSplitLineageHandler;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;

/**
 * Direct integration test for the {@see RecordMemberSplitLineageHandler}
 * Messenger handler (LRA-209). Mirrors
 * {@see \App\Tests\Integration\Households\Event\RecordMemberMergeLineageHandlerTest}:
 * verifies a dispatched {@see MemberSplitOff} appends one SPLIT_FROM row
 * to the shared `household_member_lineage` audit table, carrying the
 * selected transaction ids.
 *
 * DAMA's transaction rollback isolates rows between cases.
 */
#[Medium]
#[Group('database')]
final class RecordMemberSplitLineageHandlerTest extends KernelTestCase
{
    private const string OCCURRED_AT = '2026-05-24 12:00:00';

    private const string HOUSEHOLD_ID  = '019571bf-5d55-7000-b500-00000000dd01';
    private const string SOURCE_ID     = '019571bf-5d55-7000-b500-00000000dd02';
    private const string SOURCE_CODE   = 'M000530';
    private const string NEW_MEMBER_ID = '019571bf-5d55-7000-b500-00000000dd03';

    private MockClock $clock;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->clock = new MockClock(new DateTimeImmutable(self::OCCURRED_AT));
        $this->seedHousehold();
    }

    #[Test]
    #[TestDox('Appends one SPLIT_FROM row carrying the selected transaction ids to household_member_lineage.')]
    public function appends_split_from_row(): void
    {
        $handler = $this->handler();

        $handler(new MemberSplitOff(
            HouseholdId::fromString(self::HOUSEHOLD_ID),
            MemberId::fromString(self::SOURCE_ID),
            MemberId::fromString(self::NEW_MEMBER_ID),
            MemberCode::of('M000531'),
            TransactionReferences::fromStrings(['txn-1', 'txn-2']),
            'Two people share one record',
            new DateTimeImmutable(self::OCCURRED_AT),
        ));

        $rows = $this->connection()->fetchAllAssociative(
            'SELECT household_id, member_id, related_household_id, related_member_id, kind, reason, '
            . 'transaction_ids FROM household_member_lineage WHERE member_id = :m ORDER BY id ASC',
            ['m' => self::NEW_MEMBER_ID],
        );

        self::assertCount(1, $rows);
        self::assertSame(self::HOUSEHOLD_ID, $rows[0]['household_id']);
        self::assertSame(self::NEW_MEMBER_ID, $rows[0]['member_id']);
        self::assertSame(self::HOUSEHOLD_ID, $rows[0]['related_household_id']);
        self::assertSame(self::SOURCE_ID, $rows[0]['related_member_id']);
        self::assertSame('SPLIT_FROM', $rows[0]['kind']);
        self::assertSame('Two people share one record', $rows[0]['reason']);
        $transactionIds = $rows[0]['transaction_ids'];
        self::assertIsString($transactionIds);
        self::assertSame(['txn-1', 'txn-2'], json_decode($transactionIds, true));
    }

    private function handler(): RecordMemberSplitLineageHandler
    {
        $handler = static::getContainer()->get(RecordMemberSplitLineageHandler::class);
        self::assertInstanceOf(RecordMemberSplitLineageHandler::class, $handler);

        return $handler;
    }

    private function connection(): Connection
    {
        $connection = static::getContainer()->get(Connection::class);
        self::assertInstanceOf(Connection::class, $connection);

        return $connection;
    }

    private function seedHousehold(): void
    {
        $repo = static::getContainer()->get(Households::class);
        self::assertInstanceOf(Households::class, $repo);

        $household = Household::register(
            HouseholdId::fromString(self::HOUSEHOLD_ID),
            HouseholdName::of('Lineage Split Family'),
            Address::of('1 Test St', null, 'Seattle', 'WA', '98101', 'US'),
            MemberId::fromString(self::SOURCE_ID),
            MemberCode::of(self::SOURCE_CODE),
            PersonName::of('Src', 'Test'),
            DateOfBirth::of(new DateTimeImmutable('1990-01-01'), $this->clock),
            Gender::Unspecified,
            null,
            null,
            ResidencyStatus::Resident,
            $this->clock,
        );
        $repo->save($household);
    }
}
