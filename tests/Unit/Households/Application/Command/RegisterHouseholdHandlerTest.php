<?php

declare(strict_types=1);

namespace App\Tests\Unit\Households\Application\Command;

use App\Households\Application\Command\RegisterHousehold;
use App\Households\Application\Command\RegisterHouseholdHandler;
use App\Households\Domain\Event\HouseholdRegistered;
use App\Households\Domain\Event\MemberAddedToHousehold;
use App\Households\Domain\ValueObject\HouseholdId;
use App\Households\Domain\ValueObject\MemberId;
use App\Households\Infrastructure\Persistence\InMemory\InMemoryHouseholds;
use App\Households\Infrastructure\Persistence\InMemory\InMemoryMemberCodeAllocator;
use App\Tests\Support\Fake\HouseholdSequenceIdentityGenerator;
use App\Tests\Support\Fake\RecordingMessageBus;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[Small]
final class RegisterHouseholdHandlerTest extends TestCase
{
    private const string HOUSEHOLD_ID = '019571bf-5d51-7000-b500-000000000a01';
    private const string MEMBER_ID = '019571bf-5d51-7000-b500-000000000a02';

    private MockClock $clock;
    private InMemoryHouseholds $households;
    private RecordingMessageBus $eventBus;
    private RegisterHouseholdHandler $handler;

    protected function setUp(): void
    {
        $this->clock = new MockClock(new DateTimeImmutable('2026-05-24 12:00:00'));
        $this->households = new InMemoryHouseholds();
        $this->eventBus = new RecordingMessageBus();
        $ids = new HouseholdSequenceIdentityGenerator(
            [HouseholdId::fromString(self::HOUSEHOLD_ID)],
            [MemberId::fromString(self::MEMBER_ID)],
        );

        $this->handler = new RegisterHouseholdHandler(
            $this->households,
            $ids,
            new InMemoryMemberCodeAllocator(),
            $this->clock,
            $this->eventBus,
        );
    }

    #[Test]
    #[TestDox('Persists the household, returns its id, and publishes registration + primary-member events.')]
    public function happy_path_persists_and_dispatches_events(): void
    {
        $command = $this->validCommand(memberCode: null);

        $id = ($this->handler)($command);

        self::assertSame(self::HOUSEHOLD_ID, $id->value);
        $stored = $this->households->findById($id);
        self::assertSame('Smith Family', $stored->name()->value);
        self::assertCount(1, $stored->members());

        $messages = $this->eventBus->dispatchedMessages();
        self::assertCount(2, $messages);
        self::assertInstanceOf(HouseholdRegistered::class, $messages[0]);
        self::assertInstanceOf(MemberAddedToHousehold::class, $messages[1]);
        self::assertSame(self::MEMBER_ID, $messages[1]->memberId->value);
        self::assertTrue($messages[1]->isPrimary);
    }

    #[Test]
    #[TestDox('Handler uses the supplied member code instead of allocating one when memberCode is provided.')]
    public function uses_supplied_member_code_when_present(): void
    {
        $command = $this->validCommand(memberCode: 'M999999');

        $id = ($this->handler)($command);

        $stored = $this->households->findById($id);
        $members = $stored->members();
        self::assertSame('M999999', $members[0]->code()->value);
    }

    private function validCommand(?string $memberCode): RegisterHousehold
    {
        return new RegisterHousehold(
            householdName: 'Smith Family',
            firstName: 'Alice',
            lastName: 'Smith',
            middleName: null,
            suffix: null,
            dobIso: '1990-01-01',
            genderCode: 'F',
            email: 'alice@example.com',
            phone: '5550100',
            residencyStatusCode: 'RESIDENT',
            memberCode: $memberCode,
            street: '100 Main St',
            unit: null,
            city: 'Seattle',
            state: 'WA',
            postalCode: '98101',
            country: 'US',
        );
    }
}
