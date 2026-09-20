<?php

declare(strict_types=1);

namespace App\Tests\Unit\Administration\Application\Query;

use App\Administration\Application\Query\SeniorityComparison;
use App\Administration\Application\Query\SeniorityComparisonHandler;
use App\Administration\Domain\Rank;
use App\Administration\Domain\ValueObject\Actor;
use App\Administration\Domain\ValueObject\AssignedRoles;
use App\Administration\Domain\ValueObject\RankId;
use App\Administration\Domain\ValueObject\RankName;
use App\Administration\Domain\ValueObject\SeniorityLevel;
use App\Administration\Infrastructure\Persistence\InMemory\InMemoryRanks;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

/**
 * AC 1 (LRA-267): "the seniority query handler has no privilege
 * dependency." This handler's constructor takes only {@see \App\Administration\Domain\Ranks} —
 * no {@see \App\Administration\Domain\PrivilegeLookup} import exists in
 * SeniorityComparisonHandler at all, which is the executable form of that
 * criterion.
 */
#[Small]
final class SeniorityComparisonHandlerTest extends TestCase
{
    private const string SENIOR_RANK_ID = '019571bf-5d51-7000-b500-00000000ba01';
    private const string JUNIOR_RANK_ID = '019571bf-5d51-7000-b500-00000000ba02';

    private InMemoryRanks $ranks;
    private MockClock $clock;

    protected function setUp(): void
    {
        $this->ranks = new InMemoryRanks();
        $this->clock = new MockClock(new DateTimeImmutable('2026-05-27 12:00:00'));
        $this->seedRank(self::SENIOR_RANK_ID, 'Director', 20);
        $this->seedRank(self::JUNIOR_RANK_ID, 'Supervisor', 40);
    }

    #[Test]
    #[TestDox('Returns true when the subject rank is at least as senior as the target rank.')]
    public function returns_true_when_subject_is_at_least_as_senior(): void
    {
        $handler = new SeniorityComparisonHandler($this->ranks);

        self::assertTrue($handler(new SeniorityComparison(self::SENIOR_RANK_ID, self::JUNIOR_RANK_ID)));
        self::assertTrue($handler(new SeniorityComparison(self::SENIOR_RANK_ID, self::SENIOR_RANK_ID)));
    }

    #[Test]
    #[TestDox('Returns false when the subject rank is less senior than the target rank.')]
    public function returns_false_when_subject_is_less_senior(): void
    {
        $handler = new SeniorityComparisonHandler($this->ranks);

        self::assertFalse($handler(new SeniorityComparison(self::JUNIOR_RANK_ID, self::SENIOR_RANK_ID)));
    }

    private function seedRank(string $id, string $name, int $seniority): void
    {
        $rank = Rank::define(
            RankId::fromString($id),
            RankName::of($name),
            SeniorityLevel::of($seniority),
            AssignedRoles::none(),
            Actor::system(),
            $this->clock,
        );
        $rank->releaseEvents();
        $this->ranks->add($rank);
    }
}
