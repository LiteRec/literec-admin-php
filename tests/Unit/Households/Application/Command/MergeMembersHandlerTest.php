<?php

declare(strict_types=1);

namespace App\Tests\Unit\Households\Application\Command;

use App\Households\Application\Command\MergeMembers;
use App\Households\Application\Command\MergeMembersHandler;
use App\Households\Domain\Event\MemberMergedInto;
use App\Households\Domain\Exception\CannotMergeMemberIntoItself;
use App\Households\Domain\Exception\MemberAlreadyMerged;
use App\Households\Domain\Exception\MemberNotFound;
use App\Households\Domain\Household;
use App\Households\Domain\HouseholdMember;
use App\Households\Domain\Households;
use App\Households\Domain\MemberMergePolicy;
use App\Households\Domain\ValueObject\Address;
use App\Households\Domain\ValueObject\DateOfBirth;
use App\Households\Domain\ValueObject\Gender;
use App\Households\Domain\ValueObject\HouseholdId;
use App\Households\Domain\ValueObject\HouseholdName;
use App\Households\Domain\ValueObject\MemberCode;
use App\Households\Domain\ValueObject\MemberContact;
use App\Households\Domain\ValueObject\MemberId;
use App\Households\Domain\ValueObject\MemberProfile;
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
final class MergeMembersHandlerTest extends TestCase
{
    private const string SURVIVOR_HOUSEHOLD_ID = '019571bf-5d54-7000-b500-000000000e01';
    private const string SURVIVOR_ID           = '019571bf-5d54-7000-b500-000000000e02';
    private const string DUPLICATE_HOUSEHOLD_ID = '019571bf-5d54-7000-b500-000000000e03';
    private const string DUPLICATE_ID          = '019571bf-5d54-7000-b500-000000000e04';
    private const string UNKNOWN_ID            = '019571bf-5d54-7000-b500-0000000000fe';

    private MockClock $clock;
    private InMemoryHouseholds $households;
    private RecordingMessageBus $eventBus;
    private MergeMembersHandler $handler;

    protected function setUp(): void
    {
        $this->clock = new MockClock(new DateTimeImmutable('2026-05-24 12:00:00'));
        $this->households = new InMemoryHouseholds();

        $this->eventBus = new RecordingMessageBus();
        $this->handler = new MergeMembersHandler(
            $this->households,
            new MemberMergePolicy(),
            $this->clock,
            $this->eventBus,
        );
    }

    #[Test]
    #[TestDox('Cross-household merge: only the duplicate\'s household is saved and MemberMergedInto is dispatched.')]
    public function cross_household_merge_saves_only_duplicate_household(): void
    {
        $this->seedSurvivorHousehold();
        $duplicateHousehold = $this->seedDuplicateHousehold();

        ($this->handler)(new MergeMembers(
            self::SURVIVOR_HOUSEHOLD_ID,
            self::SURVIVOR_ID,
            self::DUPLICATE_ID,
        ));

        $messages = $this->eventBus->dispatchedMessages();
        self::assertCount(1, $messages);
        self::assertInstanceOf(MemberMergedInto::class, $messages[0]);
        self::assertTrue($messages[0]->survivorMemberId->equals(MemberId::fromString(self::SURVIVOR_ID)));

        $reloadedDuplicate = $this->households->findById(HouseholdId::fromString(self::DUPLICATE_HOUSEHOLD_ID));
        $duplicateMember = $this->memberById($reloadedDuplicate, self::DUPLICATE_ID);
        self::assertTrue($duplicateMember->lifecycle()->isMerged());
        unset($duplicateHousehold);
    }

    #[Test]
    #[TestDox('Same-household merge: both members belong to the one household and the merge still succeeds.')]
    public function same_household_merge_succeeds(): void
    {
        $household = $this->seedSurvivorHousehold();
        $household->addMember(
            MemberId::fromString(self::DUPLICATE_ID),
            MemberCode::of('M000E02'),
            MemberProfile::of(
                PersonName::of('Dana', 'Duplicate'),
                DateOfBirth::of(new DateTimeImmutable('1991-02-02'), $this->clock),
                Gender::Female,
            ),
            MemberContact::none(),
            ResidencyStatus::Resident,
            false,
            $this->clock,
        );
        $household->releaseEvents();
        $this->households->save($household);

        ($this->handler)(new MergeMembers(
            self::SURVIVOR_HOUSEHOLD_ID,
            self::SURVIVOR_ID,
            self::DUPLICATE_ID,
        ));

        $reloaded = $this->households->findById(HouseholdId::fromString(self::SURVIVOR_HOUSEHOLD_ID));
        $duplicateMember = $this->memberById($reloaded, self::DUPLICATE_ID);
        self::assertTrue($duplicateMember->lifecycle()->isMerged());
    }

    #[Test]
    #[TestDox('Throws MemberNotFound when the survivor id does not belong to the survivor household.')]
    public function rejects_unknown_survivor(): void
    {
        $this->seedSurvivorHousehold();
        $this->seedDuplicateHousehold();

        $this->expectException(MemberNotFound::class);

        ($this->handler)(new MergeMembers(
            self::SURVIVOR_HOUSEHOLD_ID,
            self::UNKNOWN_ID,
            self::DUPLICATE_ID,
        ));
    }

    #[Test]
    #[TestDox('Throws MemberAlreadyMerged when the survivor is already merged.')]
    public function rejects_already_merged_survivor(): void
    {
        $survivorHousehold = $this->seedSurvivorHousehold();
        $survivorHousehold->member(MemberId::fromString(self::SURVIVOR_ID))->mergeInto(
            HouseholdId::fromString('019571bf-5d54-7000-b500-000000000e99'),
            MemberId::fromString('019571bf-5d54-7000-b500-000000000e98'),
            $this->clock,
        );
        $survivorHousehold->releaseEvents();
        $this->households->save($survivorHousehold);
        $this->seedDuplicateHousehold();

        $this->expectException(MemberAlreadyMerged::class);

        ($this->handler)(new MergeMembers(
            self::SURVIVOR_HOUSEHOLD_ID,
            self::SURVIVOR_ID,
            self::DUPLICATE_ID,
        ));
    }

    #[Test]
    #[TestDox('Throws CannotMergeMemberIntoItself when survivor and duplicate ids match.')]
    public function rejects_self_merge(): void
    {
        $this->seedSurvivorHousehold();

        $this->expectException(CannotMergeMemberIntoItself::class);

        ($this->handler)(new MergeMembers(
            self::SURVIVOR_HOUSEHOLD_ID,
            self::SURVIVOR_ID,
            self::SURVIVOR_ID,
        ));
    }

    #[Test]
    #[TestDox('Throws MemberAlreadyMerged when the survivor is merged concurrently before the write-time lock.')]
    public function rejects_survivor_merged_concurrently_between_read_check_and_lock(): void
    {
        $this->seedSurvivorHousehold();
        $this->seedDuplicateHousehold();
        $interloperHouseholdId = HouseholdId::fromString('019571bf-5d54-7000-b500-000000000e97');
        $interloperId = MemberId::fromString('019571bf-5d54-7000-b500-000000000e96');
        $interloper = Household::register(
            $interloperHouseholdId,
            HouseholdName::of('Interloper Family'),
            Address::of('300 Pine St', null, 'Tacoma', 'WA', '98402', 'US'),
            $interloperId,
            MemberCode::of('M000E05'),
            MemberProfile::of(
                PersonName::of('Ian', 'Interloper'),
                DateOfBirth::of(new DateTimeImmutable('1993-03-03'), $this->clock),
                Gender::Male,
            ),
            MemberContact::none(),
            ResidencyStatus::Resident,
            $this->clock,
        );
        $interloper->releaseEvents();
        $this->households->save($interloper);

        $racyHandler = new MergeMembersHandler(
            new SimulatesConcurrentMergeHouseholds(
                $this->households,
                HouseholdId::fromString(self::SURVIVOR_HOUSEHOLD_ID),
                MemberId::fromString(self::SURVIVOR_ID),
                $interloperHouseholdId,
                $interloperId,
                $this->clock,
            ),
            new MemberMergePolicy(),
            $this->clock,
            $this->eventBus,
        );

        $this->expectException(MemberAlreadyMerged::class);

        $racyHandler(new MergeMembers(self::SURVIVOR_HOUSEHOLD_ID, self::SURVIVOR_ID, self::DUPLICATE_ID));
    }

    private function seedSurvivorHousehold(): Household
    {
        $household = Household::register(
            HouseholdId::fromString(self::SURVIVOR_HOUSEHOLD_ID),
            HouseholdName::of('Survivor Family'),
            Address::of('100 Main St', null, 'Seattle', 'WA', '98101', 'US'),
            MemberId::fromString(self::SURVIVOR_ID),
            MemberCode::of('M000E01'),
            MemberProfile::of(
                PersonName::of('Sam', 'Survivor'),
                DateOfBirth::of(new DateTimeImmutable('1990-01-01'), $this->clock),
                Gender::Male,
            ),
            MemberContact::none(),
            ResidencyStatus::Resident,
            $this->clock,
        );
        $household->releaseEvents();
        $this->households->save($household);

        return $household;
    }

    private function seedDuplicateHousehold(): Household
    {
        $household = Household::register(
            HouseholdId::fromString(self::DUPLICATE_HOUSEHOLD_ID),
            HouseholdName::of('Duplicate Family'),
            Address::of('200 Oak Ave', null, 'Portland', 'OR', '97201', 'US'),
            MemberId::fromString(self::DUPLICATE_ID),
            MemberCode::of('M000E03'),
            MemberProfile::of(
                PersonName::of('Dana', 'Duplicate'),
                DateOfBirth::of(new DateTimeImmutable('1991-02-02'), $this->clock),
                Gender::Female,
            ),
            MemberContact::of(EmailAddress::of('dana@example.com'), PhoneNumber::of('5559998')),
            ResidencyStatus::Resident,
            $this->clock,
        );
        $household->releaseEvents();
        $this->households->save($household);

        return $household;
    }

    private function memberById(Household $household, string $memberId): HouseholdMember
    {
        $needle = MemberId::fromString($memberId);
        $matches = array_values(array_filter(
            $household->members(),
            static fn(HouseholdMember $member): bool => $member->id()->equals($needle),
        ));

        self::assertNotEmpty($matches, sprintf('Member %s not found in household.', $memberId));

        return $matches[0];
    }
}
