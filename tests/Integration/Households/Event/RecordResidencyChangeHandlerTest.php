<?php

declare(strict_types=1);

namespace App\Tests\Integration\Households\Event;

use App\Households\Domain\Event\MemberResidencyChanged;
use App\Households\Domain\Household;
use App\Households\Domain\Households;
use App\Households\Domain\ValueObject\Address;
use App\Households\Domain\ValueObject\DateOfBirth;
use App\Shared\Domain\ValueObject\EmailAddress;
use App\Households\Domain\ValueObject\Gender;
use App\Households\Domain\ValueObject\HouseholdId;
use App\Households\Domain\ValueObject\HouseholdName;
use App\Households\Domain\ValueObject\MemberCode;
use App\Households\Domain\ValueObject\MemberId;
use App\Households\Domain\ValueObject\PersonName;
use App\Households\Domain\ValueObject\ResidencyStatus;
use App\Households\Infrastructure\Persistence\Doctrine\Event\RecordResidencyChangeHandler;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;

/**
 * Direct integration test for the {@see RecordResidencyChangeHandler}
 * Messenger handler (LRA-44). Verifies that each dispatched
 * {@see MemberResidencyChanged} event appends one row to
 * `household_residency_history`, preserving prior rows (append-only).
 *
 * DAMA's transaction rollback isolates rows between cases.
 */
#[Medium]
#[Group('database')]
final class RecordResidencyChangeHandlerTest extends KernelTestCase
{
    /** Reused literals (SonarCloud php:S1192). */
    private const string RECORDED_AT = '2026-05-24 12:00:00';
    private const string EFFECTIVE_FROM = '2026-05-01 00:00:00';

    private const string HOUSEHOLD_ID = '019571bf-5d55-7000-b500-00000000bb01';
    private const string MEMBER_ID    = '019571bf-5d55-7000-b500-00000000bb02';
    private const string MEMBER_CODE  = 'M000510';

    private MockClock $clock;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->clock = new MockClock(new DateTimeImmutable(self::RECORDED_AT));
        $this->seedHousehold();
    }

    #[Test]
    #[TestDox('Appends one history row per dispatched event, preserving prior rows.')]
    public function appends_row_for_each_event(): void
    {
        $handler = $this->handler();

        $handler(new MemberResidencyChanged(
            HouseholdId::fromString(self::HOUSEHOLD_ID),
            MemberId::fromString(self::MEMBER_ID),
            ResidencyStatus::Member,
            new DateTimeImmutable(self::EFFECTIVE_FROM),
            new DateTimeImmutable(self::RECORDED_AT),
            'initial member upgrade',
        ));

        $handler(new MemberResidencyChanged(
            HouseholdId::fromString(self::HOUSEHOLD_ID),
            MemberId::fromString(self::MEMBER_ID),
            ResidencyStatus::Staff,
            new DateTimeImmutable('2026-06-01 00:00:00'),
            new DateTimeImmutable('2026-06-01 09:00:00'),
            'hired',
        ));

        $rows = $this->connection()->fetchAllAssociative(
            'SELECT status, reason, effective_from FROM household_residency_history '
            . 'WHERE member_id = :m ORDER BY id ASC',
            ['m' => self::MEMBER_ID],
        );

        self::assertCount(2, $rows);
        self::assertSame('MEMBER', $rows[0]['status']);
        self::assertSame('initial member upgrade', $rows[0]['reason']);
        self::assertSame(self::EFFECTIVE_FROM, $rows[0]['effective_from']);
        self::assertSame('STAFF', $rows[1]['status']);
        self::assertSame('hired', $rows[1]['reason']);
        self::assertSame('2026-06-01 00:00:00', $rows[1]['effective_from']);
    }

    #[Test]
    #[TestDox('Persists a null reason as a NULL column when the event reason is null.')]
    public function copes_with_null_reason(): void
    {
        $handler = $this->handler();

        $handler(new MemberResidencyChanged(
            HouseholdId::fromString(self::HOUSEHOLD_ID),
            MemberId::fromString(self::MEMBER_ID),
            ResidencyStatus::NonResident,
            new DateTimeImmutable(self::EFFECTIVE_FROM),
            new DateTimeImmutable(self::RECORDED_AT),
            null,
        ));

        $row = $this->connection()->fetchAssociative(
            'SELECT reason FROM household_residency_history '
            . 'WHERE member_id = :m ORDER BY id DESC LIMIT 1',
            ['m' => self::MEMBER_ID],
        );

        self::assertNotFalse($row);
        self::assertNull($row['reason']);
    }

    private function handler(): RecordResidencyChangeHandler
    {
        $handler = static::getContainer()->get(RecordResidencyChangeHandler::class);
        self::assertInstanceOf(RecordResidencyChangeHandler::class, $handler);

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
            HouseholdName::of('Residency Test Family'),
            Address::of('1 Test St', null, 'Seattle', 'WA', '98101', 'US'),
            MemberId::fromString(self::MEMBER_ID),
            MemberCode::of(self::MEMBER_CODE),
            PersonName::of('Resi', 'Test'),
            DateOfBirth::of(new DateTimeImmutable('1990-01-01'), $this->clock),
            Gender::Unspecified,
            EmailAddress::of('resi@example.com'),
            null,
            ResidencyStatus::Resident,
            $this->clock,
        );

        $repo->save($household);
    }
}
