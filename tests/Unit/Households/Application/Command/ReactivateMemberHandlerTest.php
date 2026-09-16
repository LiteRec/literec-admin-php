<?php

declare(strict_types=1);

namespace App\Tests\Unit\Households\Application\Command;

use App\Households\Application\Command\DeactivateMember;
use App\Households\Application\Command\DeactivateMemberHandler;
use App\Households\Application\Command\ReactivateMember;
use App\Households\Application\Command\ReactivateMemberHandler;
use App\Households\Domain\Event\MemberReactivated;
use App\Households\Domain\Exception\HouseholdNotFound;
use App\Households\Domain\Exception\MemberNotFound;
use App\Households\Domain\ValueObject\HouseholdId;
use App\Households\Domain\ValueObject\MemberCode;
use App\Households\Domain\ValueObject\MemberId;
use App\Households\Infrastructure\Persistence\InMemory\InMemoryHouseholds;
use App\Tests\Support\Fake\RecordingMessageBus;
use App\Tests\Support\Trait\SeedsAliceSmithHousehold;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[Small]
final class ReactivateMemberHandlerTest extends TestCase
{
    use SeedsAliceSmithHousehold;

    private const string HOUSEHOLD_ID = '019571bf-5d54-7000-b500-000000000e01';
    private const string PRIMARY_ID   = '019571bf-5d54-7000-b500-000000000e02';
    private const string PRIMARY_CODE = 'M000500';
    private const string UNKNOWN_ID   = '019571bf-5d54-7000-b500-0000000000fe';
    private const string UNKNOWN_HOUSEHOLD_ID = '019571bf-5d54-7000-b500-0000000000ff';

    private MockClock $clock;
    private InMemoryHouseholds $households;
    private RecordingMessageBus $eventBus;
    private ReactivateMemberHandler $handler;

    protected function setUp(): void
    {
        $this->clock = new MockClock(new DateTimeImmutable('2026-05-24 12:00:00'));
        $this->households = new InMemoryHouseholds();
        $seed = $this->seedAliceSmithHousehold(
            HouseholdId::fromString(self::HOUSEHOLD_ID),
            MemberId::fromString(self::PRIMARY_ID),
            MemberCode::of(self::PRIMARY_CODE),
            $this->clock,
        );
        // Drain registration events so each test sees only what the handler
        // under test publishes.
        $seed->releaseEvents();
        $this->households->save($seed);

        $this->eventBus = new RecordingMessageBus();
        $this->handler = new ReactivateMemberHandler(
            $this->households,
            $this->clock,
            $this->eventBus,
        );
    }

    #[Test]
    #[TestDox('Reactivates a deactivated member and publishes exactly one MemberReactivated.')]
    public function happy_path_reactivates_member_and_dispatches_event(): void
    {
        $this->deactivatePrimaryMember();

        ($this->handler)(new ReactivateMember(
            householdId: self::HOUSEHOLD_ID,
            memberId: self::PRIMARY_ID,
        ));

        $stored = $this->households->findById(HouseholdId::fromString(self::HOUSEHOLD_ID));
        $member = $this->memberById($stored, self::PRIMARY_ID);
        self::assertTrue($member->lifecycle()->isActive);

        $messages = $this->eventBus->dispatchedMessages();
        self::assertCount(1, $messages);
        self::assertInstanceOf(MemberReactivated::class, $messages[0]);
    }

    #[Test]
    #[TestDox('Reactivating an already-active member is idempotent: no event is published.')]
    public function second_call_is_idempotent_and_publishes_nothing(): void
    {
        // Member is already active from seeding; reactivating a no-op.
        ($this->handler)(new ReactivateMember(
            householdId: self::HOUSEHOLD_ID,
            memberId: self::PRIMARY_ID,
        ));

        self::assertSame([], $this->eventBus->dispatchedMessages());
    }

    #[Test]
    #[TestDox('Throws MemberNotFound when the memberId does not exist in the household.')]
    public function rejects_unknown_member(): void
    {
        $this->expectException(MemberNotFound::class);

        ($this->handler)(new ReactivateMember(
            householdId: self::HOUSEHOLD_ID,
            memberId: self::UNKNOWN_ID,
        ));
    }

    #[Test]
    #[TestDox('Throws HouseholdNotFound when the householdId does not exist.')]
    public function rejects_unknown_household(): void
    {
        $this->expectException(HouseholdNotFound::class);

        ($this->handler)(new ReactivateMember(
            householdId: self::UNKNOWN_HOUSEHOLD_ID,
            memberId: self::PRIMARY_ID,
        ));
    }

    private function deactivatePrimaryMember(): void
    {
        $deactivateHandler = new DeactivateMemberHandler($this->households, $this->clock, $this->eventBus);
        ($deactivateHandler)(new DeactivateMember(
            householdId: self::HOUSEHOLD_ID,
            memberId: self::PRIMARY_ID,
            reason: 'Moved out of state',
        ));
        // Only the ReactivateMember publication is under test.
        $this->eventBus = new RecordingMessageBus();
        $this->handler = new ReactivateMemberHandler($this->households, $this->clock, $this->eventBus);
    }
}
