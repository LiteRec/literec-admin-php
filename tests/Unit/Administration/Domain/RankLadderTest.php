<?php

declare(strict_types=1);

namespace App\Tests\Unit\Administration\Domain;

use App\Administration\Domain\Rank;
use App\Administration\Domain\ValueObject\Actor;
use App\Administration\Domain\ValueObject\AssignedRoles;
use App\Administration\Domain\ValueObject\RankId;
use App\Administration\Domain\ValueObject\RankName;
use App\Administration\Domain\ValueObject\RoleId;
use App\Administration\Domain\ValueObject\SeniorityLevel;
use App\Administration\Infrastructure\Fixtures\RankLadderFixtures;
use App\Administration\Infrastructure\Persistence\InMemory\InMemoryRanks;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

/**
 * AC 3 (LRA-267): "The job levels both agencies actually use can be
 * expressed by the seeded ladder without a bespoke rank per person; two
 * ranks may share a seniority level and differ only in assigned roles."
 */
#[Small]
final class RankLadderTest extends TestCase
{
    private const array EXPECTED_LADDER = [
        0 => 'Vendor Support',
        10 => 'System Administrator',
        20 => 'Director',
        30 => 'Manager',
        40 => 'Supervisor',
        50 => 'Program Coordinator',
        60 => 'Administrator',
        70 => 'Customer Support Representative',
        80 => 'Communications',
        90 => 'Reporting and Monitoring',
        100 => 'Volunteer',
    ];

    #[Test]
    #[TestDox('The default ladder covers exactly the eleven job levels the legacy agencies use.')]
    public function ladder_covers_every_expected_job_level(): void
    {
        self::assertSame(self::EXPECTED_LADDER, RankLadderFixtures::LADDER);
    }

    #[Test]
    #[TestDox('No two rungs of the default ladder share a name.')]
    public function no_two_rungs_share_a_name(): void
    {
        $names = array_values(RankLadderFixtures::LADDER);

        self::assertCount(count($names), array_unique($names));
    }

    #[Test]
    #[TestDox('Every rung is a valid RankName and SeniorityLevel — nothing in the ladder is rejected.')]
    public function every_rung_is_a_valid_rank_name_and_seniority_level(): void
    {
        foreach (RankLadderFixtures::LADDER as $seniority => $name) {
            self::assertSame($name, RankName::of($name)->value);
            self::assertSame($seniority, SeniorityLevel::of($seniority)->value);
        }
    }

    #[Test]
    #[TestDox('Two ranks may share a seniority level and differ only in assigned roles.')]
    public function two_ranks_may_share_a_seniority_level(): void
    {
        $clock = new MockClock(new DateTimeImmutable('2026-05-27 12:00:00'));
        $ranks = new InMemoryRanks();
        $seniority = SeniorityLevel::of(22);
        $northRole = RoleId::fromString('019571bf-5d51-7000-b500-0000000fad01');

        $first = Rank::define(
            RankId::fromString('019571bf-5d51-7000-b500-0000000fac01'),
            RankName::of('Facility Manager North'),
            $seniority,
            AssignedRoles::of($northRole),
            Actor::system(),
            $clock,
        );
        $second = Rank::define(
            RankId::fromString('019571bf-5d51-7000-b500-0000000fac02'),
            RankName::of('Facility Manager South'),
            $seniority,
            AssignedRoles::none(),
            Actor::system(),
            $clock,
        );
        $first->releaseEvents();
        $second->releaseEvents();

        $ranks->add($first);
        $ranks->add($second);

        self::assertTrue(
            $ranks->byId($first->id())->seniority()->equals($ranks->byId($second->id())->seniority()),
        );
        self::assertFalse($first->name()->equals($second->name()));
        self::assertFalse($first->roles()->equals($second->roles()));
        self::assertTrue($first->roles()->contains($northRole));
        self::assertFalse($second->roles()->contains($northRole));
    }
}
