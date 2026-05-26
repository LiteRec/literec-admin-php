<?php

declare(strict_types=1);

namespace App\Tests\Unit\Households\Application\Command;

use App\Households\Application\Command\UpdateMemberProfile;
use App\Households\Application\Command\UpdateMemberProfileHandler;
use App\Households\Domain\Event\MemberProfileUpdated;
use App\Households\Domain\Exception\MemberNotFound;
use App\Households\Domain\Household;
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
use App\Households\Infrastructure\Persistence\InMemory\InMemoryHouseholds;
use App\Tests\Support\Fake\RecordingMessageBus;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[Small]
final class UpdateMemberProfileHandlerTest extends TestCase
{
    private const string HOUSEHOLD_ID  = '019571bf-5d54-7000-b500-000000000c01';
    private const string PRIMARY_ID    = '019571bf-5d54-7000-b500-000000000c02';
    private const string PRIMARY_CODE  = 'M000300';
    private const string UNKNOWN_ID    = '019571bf-5d54-7000-b500-0000000000fe';

    private MockClock $clock;
    private InMemoryHouseholds $households;
    private RecordingMessageBus $eventBus;
    private UpdateMemberProfileHandler $handler;

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
        $this->handler = new UpdateMemberProfileHandler(
            $this->households,
            $this->clock,
            $this->eventBus,
        );
    }

    #[Test]
    #[TestDox('Updates the profile and publishes MemberProfileUpdated when fields actually change.')]
    public function happy_path_updates_profile_and_dispatches_event(): void
    {
        $command = new UpdateMemberProfile(
            householdId: self::HOUSEHOLD_ID,
            memberId: self::PRIMARY_ID,
            firstName: 'Alicia',
            lastName: 'Smith-Jones',
            middleName: 'Renee',
            suffix: null,
            dobIso: '1990-02-02',
            genderCode: 'F',
        );

        ($this->handler)($command);

        $stored = $this->households->findById(HouseholdId::fromString(self::HOUSEHOLD_ID));
        $member = $this->memberById($stored, self::PRIMARY_ID);
        self::assertSame('Alicia', $member->name()->firstName);
        self::assertSame('Smith-Jones', $member->name()->lastName);
        self::assertSame('Renee', $member->name()->middleName);
        self::assertSame('1990-02-02', $member->dateOfBirth()->value()->format('Y-m-d'));

        $messages = $this->eventBus->dispatchedMessages();
        self::assertCount(1, $messages);
        self::assertInstanceOf(MemberProfileUpdated::class, $messages[0]);
    }

    #[Test]
    #[TestDox('Submitting unchanged values is a no-op: no event is dispatched.')]
    public function no_op_when_values_are_unchanged(): void
    {
        $command = new UpdateMemberProfile(
            householdId: self::HOUSEHOLD_ID,
            memberId: self::PRIMARY_ID,
            firstName: 'Alice',
            lastName: 'Smith',
            middleName: null,
            suffix: null,
            dobIso: '1990-01-01',
            genderCode: 'F',
        );

        ($this->handler)($command);

        self::assertSame([], $this->eventBus->dispatchedMessages());
    }

    #[Test]
    #[TestDox('Throws MemberNotFound when the memberId does not exist in the household.')]
    public function rejects_unknown_member(): void
    {
        $command = new UpdateMemberProfile(
            householdId: self::HOUSEHOLD_ID,
            memberId: self::UNKNOWN_ID,
            firstName: 'Ghost',
            lastName: 'User',
            middleName: null,
            suffix: null,
            dobIso: '1990-01-01',
            genderCode: 'U',
        );

        $this->expectException(MemberNotFound::class);

        ($this->handler)($command);
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
            HouseholdName::of('Smith Family'),
            Address::of('100 Main St', null, 'Seattle', 'WA', '98101', 'US'),
            MemberId::fromString(self::PRIMARY_ID),
            MemberCode::of(self::PRIMARY_CODE),
            PersonName::of('Alice', 'Smith'),
            DateOfBirth::of(new DateTimeImmutable('1990-01-01'), $this->clock),
            Gender::Female,
            EmailAddress::of('alice@example.com'),
            null,
            ResidencyStatus::Resident,
            $this->clock,
        );
    }
}
