<?php

declare(strict_types=1);

namespace App\Tests\Unit\Households\Domain;

use App\Households\Domain\Exception\InactiveSurvivorCannotAcceptMerge;
use App\Households\Domain\Exception\MemberAlreadyMerged;
use App\Households\Domain\Exception\MemberNotFound;
use App\Households\Domain\Household;
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
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[Small]
final class MemberMergePolicyTest extends TestCase
{
    private const string HOUSEHOLD_ID = '019571bf-5d51-7000-b500-000000000010';
    private const string SURVIVOR_ID = '019571bf-5d51-7000-b500-000000000011';

    private MockClock $clock;
    private MemberMergePolicy $policy;

    protected function setUp(): void
    {
        $this->clock = new MockClock(new DateTimeImmutable('2026-01-01 12:00:00'));
        $this->policy = new MemberMergePolicy();
    }

    #[Test]
    #[TestDox('::assertSurvivorAccepts() does not throw when the survivor exists and is not merged.')]
    public function accepts_a_known_unmerged_survivor(): void
    {
        $household = $this->registerHousehold();

        $this->policy->assertSurvivorAccepts($household, MemberId::fromString(self::SURVIVOR_ID));

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    #[TestDox('::assertSurvivorAccepts() throws MemberNotFound when the household has no such member.')]
    public function rejects_an_unknown_survivor(): void
    {
        $household = $this->registerHousehold();

        $this->expectException(MemberNotFound::class);

        $this->policy->assertSurvivorAccepts(
            $household,
            MemberId::fromString('019571bf-5d51-7000-b500-0000000000ff'),
        );
    }

    #[Test]
    #[TestDox('::assertSurvivorAccepts() throws MemberAlreadyMerged when the survivor is already merged.')]
    public function rejects_an_already_merged_survivor(): void
    {
        $household = $this->registerHousehold();
        $survivorId = MemberId::fromString(self::SURVIVOR_ID);
        $household->mergeMemberInto(
            $survivorId,
            HouseholdId::fromString('019571bf-5d51-7000-b500-000000000099'),
            MemberId::fromString('019571bf-5d51-7000-b500-000000000098'),
            $this->clock,
        );

        $this->expectException(MemberAlreadyMerged::class);

        $this->policy->assertSurvivorAccepts($household, $survivorId);
    }

    #[Test]
    #[TestDox('::assertSurvivorAccepts() throws InactiveSurvivorCannotAcceptMerge when the survivor is deactivated.')]
    public function rejects_a_deactivated_survivor(): void
    {
        $household = $this->registerHousehold();
        $survivorId = MemberId::fromString(self::SURVIVOR_ID);
        $household->deactivateMember($survivorId, 'moved away', $this->clock);

        $this->expectException(InactiveSurvivorCannotAcceptMerge::class);

        $this->policy->assertSurvivorAccepts($household, $survivorId);
    }

    private function registerHousehold(): Household
    {
        return Household::register(
            HouseholdId::fromString(self::HOUSEHOLD_ID),
            HouseholdName::of('Survivor Family'),
            Address::of('123 Main St', null, 'Springfield', 'IL', '62701', 'US'),
            MemberId::fromString(self::SURVIVOR_ID),
            MemberCode::of('M0001'),
            MemberProfile::of(
                PersonName::of('Sam', 'Survivor'),
                DateOfBirth::of(new DateTimeImmutable('1990-01-01'), $this->clock),
                Gender::Male,
            ),
            MemberContact::none(),
            ResidencyStatus::Resident,
            $this->clock,
        );
    }
}
