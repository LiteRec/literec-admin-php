<?php

declare(strict_types=1);

namespace App\Tests\Unit\Households\Application\Command;

use App\Households\Application\Command\DeactivateMember;
use App\Households\Application\Command\DeactivateMemberHandler;
use App\Households\Domain\Event\MemberDeactivated;
use App\Households\Domain\Exception\HouseholdNotFound;
use App\Households\Domain\Exception\MemberNotFound;
use App\Households\Domain\Household;
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
use App\Tests\Support\Fake\RecordingMessageBus;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[Small]
final class DeactivateMemberHandlerTest extends TestCase
{
    private const string HOUSEHOLD_ID = '019571bf-5d54-7000-b500-000000000d01';
    private const string PRIMARY_ID   = '019571bf-5d54-7000-b500-000000000d02';
    private const string PRIMARY_CODE = 'M000400';
    private const string UNKNOWN_ID   = '019571bf-5d54-7000-b500-0000000000fe';
    private const string UNKNOWN_HOUSEHOLD_ID = '019571bf-5d54-7000-b500-0000000000ff';

    private MockClock $clock;
    private InMemoryHouseholds $households;
    private RecordingMessageBus $eventBus;
    private DeactivateMemberHandler $handler;

    protected function setUp(): void
    {
        $this->clock = new MockClock(new DateTimeImmutable('2026-05-24 12:00:00'));
        $this->households = new InMemoryHouseholds();
        $seed = $this->seedHousehold();
        // Drain registration events so each test sees only what the handler
        // under test publishes.
        $seed->releaseEvents();
        $this->households->save($seed);

        $this->eventBus = new RecordingMessageBus();
        $this->handler = new DeactivateMemberHandler(
            $this->households,
            $this->clock,
            $this->eventBus,
        );
    }

    #[Test]
    #[TestDox('Deactivates an active member and publishes exactly one MemberDeactivated.')]
    public function happy_path_deactivates_member_and_dispatches_event(): void
    {
        ($this->handler)(new DeactivateMember(
            householdId: self::HOUSEHOLD_ID,
            memberId: self::PRIMARY_ID,
            reason: 'Moved out of state',
        ));

        $stored = $this->households->findById(HouseholdId::fromString(self::HOUSEHOLD_ID));
        $member = $this->memberById($stored, self::PRIMARY_ID);
        self::assertFalse($member->isActive());
        self::assertSame('Moved out of state', $member->deactivation()?->reason);

        $messages = $this->eventBus->dispatchedMessages();
        self::assertCount(1, $messages);
        self::assertInstanceOf(MemberDeactivated::class, $messages[0]);
    }

    #[Test]
    #[TestDox('Deactivating an already-deactivated member is idempotent: no event is published.')]
    public function second_call_is_idempotent_and_publishes_nothing(): void
    {
        ($this->handler)(new DeactivateMember(
            householdId: self::HOUSEHOLD_ID,
            memberId: self::PRIMARY_ID,
            reason: 'Moved out of state',
        ));

        ($this->handler)(new DeactivateMember(
            householdId: self::HOUSEHOLD_ID,
            memberId: self::PRIMARY_ID,
            reason: 'Second attempt',
        ));

        self::assertCount(1, $this->eventBus->dispatchedMessages());
    }

    #[Test]
    #[TestDox('Throws MemberNotFound when the memberId does not exist in the household.')]
    public function rejects_unknown_member(): void
    {
        $this->expectException(MemberNotFound::class);

        ($this->handler)(new DeactivateMember(
            householdId: self::HOUSEHOLD_ID,
            memberId: self::UNKNOWN_ID,
            reason: 'Ghost',
        ));
    }

    #[Test]
    #[TestDox('Throws HouseholdNotFound when the householdId does not exist.')]
    public function rejects_unknown_household(): void
    {
        $this->expectException(HouseholdNotFound::class);

        ($this->handler)(new DeactivateMember(
            householdId: self::UNKNOWN_HOUSEHOLD_ID,
            memberId: self::PRIMARY_ID,
            reason: 'Ghost household',
        ));
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

    private function seedHousehold(): Household
    {
        return Household::register(
            HouseholdId::fromString(self::HOUSEHOLD_ID),
            HouseholdName::of('Deactivation Family'),
            Address::of('100 Main St', null, 'Seattle', 'WA', '98101', 'US'),
            MemberId::fromString(self::PRIMARY_ID),
            MemberCode::of(self::PRIMARY_CODE),
            PersonName::of('Dana', 'Deactivate'),
            DateOfBirth::of(new DateTimeImmutable('1990-01-01'), $this->clock),
            Gender::Female,
            EmailAddress::of('dana@example.com'),
            null,
            ResidencyStatus::Resident,
            $this->clock,
        );
    }
}
