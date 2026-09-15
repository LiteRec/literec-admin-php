<?php

declare(strict_types=1);

namespace App\Tests\Unit\Households\Application\Command;

use App\Households\Application\Command\WithdrawMinorFromHousehold;
use App\Households\Application\Command\WithdrawMinorFromHouseholdHandler;
use App\Households\Domain\Event\MemberSharingWithdrawn;
use App\Households\Domain\Household;
use App\Households\Domain\HouseholdMember;
use App\Households\Domain\ValueObject\Address;
use App\Households\Domain\ValueObject\DateOfBirth;
use App\Households\Domain\ValueObject\Gender;
use App\Households\Domain\ValueObject\HouseholdId;
use App\Households\Domain\ValueObject\HouseholdName;
use App\Households\Domain\ValueObject\MemberCode;
use App\Households\Domain\ValueObject\MemberId;
use App\Households\Domain\ValueObject\PersonName;
use App\Households\Domain\ValueObject\ResidencyStatus;
use App\Households\Infrastructure\Persistence\InMemory\InMemoryHouseholds;
use App\Shared\Domain\ValueObject\EmailAddress;
use App\Shared\Domain\ValueObject\PhoneNumber;
use App\Tests\Support\Fake\RecordingMessageBus;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[Small]
final class WithdrawMinorFromHouseholdHandlerTest extends TestCase
{
    private const string HOME_HOUSEHOLD_ID   = '019571bf-5d54-7000-b500-000000000f01';
    private const string PRIMARY_ID          = '019571bf-5d54-7000-b500-000000000f02';
    private const string MINOR_ID            = '019571bf-5d54-7000-b500-000000000f03';
    private const string TARGET_HOUSEHOLD_ID = '019571bf-5d54-7000-b500-000000000f04';

    private MockClock $clock;
    private InMemoryHouseholds $households;
    private RecordingMessageBus $eventBus;
    private WithdrawMinorFromHouseholdHandler $handler;

    protected function setUp(): void
    {
        $this->clock = new MockClock(new DateTimeImmutable('2026-05-24 12:00:00'));
        $this->households = new InMemoryHouseholds();

        $home = $this->seedHomeHouseholdSharedWithTarget();
        $home->releaseEvents();
        $this->households->save($home);

        $this->eventBus = new RecordingMessageBus();
        $this->handler = new WithdrawMinorFromHouseholdHandler($this->households, $this->clock, $this->eventBus);
    }

    #[Test]
    #[TestDox('Withdraws the share and dispatches exactly one MemberSharingWithdrawn.')]
    public function happy_path_withdraws_share_and_dispatches_event(): void
    {
        ($this->handler)(new WithdrawMinorFromHousehold(self::TARGET_HOUSEHOLD_ID, self::MINOR_ID));

        $home = $this->households->findById(HouseholdId::fromString(self::HOME_HOUSEHOLD_ID));
        $minor = $this->memberById($home, self::MINOR_ID);
        self::assertFalse($minor->isSharedWith(HouseholdId::fromString(self::TARGET_HOUSEHOLD_ID)));

        $messages = $this->eventBus->dispatchedMessages();
        self::assertCount(1, $messages);
        self::assertInstanceOf(MemberSharingWithdrawn::class, $messages[0]);
    }

    #[Test]
    #[TestDox('Is a no-op (no event) when the member is not currently shared with the household.')]
    public function no_op_when_not_shared(): void
    {
        ($this->handler)(new WithdrawMinorFromHousehold(
            '019571bf-5d54-7000-b500-000000000f05',
            self::MINOR_ID,
        ));

        self::assertSame([], $this->eventBus->dispatchedMessages());
    }

    private function memberById(Household $household, string $memberId): HouseholdMember
    {
        $needle = MemberId::fromString($memberId);
        foreach ($household->members() as $member) {
            if ($member->id()->equals($needle)) {
                return $member;
            }
        }
        self::fail(sprintf('Member %s not found in household.', $memberId));
    }

    private function seedHomeHouseholdSharedWithTarget(): Household
    {
        $address = Address::of('100 Main St', null, 'Seattle', 'WA', '98101', 'US');

        $home = Household::register(
            HouseholdId::fromString(self::HOME_HOUSEHOLD_ID),
            HouseholdName::of('Smith Family'),
            $address,
            MemberId::fromString(self::PRIMARY_ID),
            MemberCode::of('M000600'),
            PersonName::of('Alice', 'Smith'),
            DateOfBirth::of(new DateTimeImmutable('1990-01-01'), $this->clock),
            Gender::Female,
            EmailAddress::of('alice@example.com'),
            PhoneNumber::of('5550001'),
            ResidencyStatus::Resident,
            $this->clock,
        );

        $home->addMember(
            MemberId::fromString(self::MINOR_ID),
            MemberCode::of('M000601'),
            PersonName::of('Timmy', 'Smith'),
            DateOfBirth::of(new DateTimeImmutable('2015-01-01'), $this->clock),
            Gender::Male,
            null,
            null,
            ResidencyStatus::Resident,
            false,
            $this->clock,
        );

        $home->shareMemberWithHousehold(
            MemberId::fromString(self::MINOR_ID),
            HouseholdId::fromString(self::TARGET_HOUSEHOLD_ID),
            $this->clock,
        );

        return $home;
    }
}
