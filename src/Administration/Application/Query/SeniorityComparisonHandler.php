<?php

declare(strict_types=1);

namespace App\Administration\Application\Query;

use App\Administration\Domain\Ranks;
use App\Administration\Domain\ValueObject\RankId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final class SeniorityComparisonHandler
{
    public function __construct(private readonly Ranks $ranks)
    {
    }

    public function __invoke(SeniorityComparison $query): bool
    {
        $subject = $this->ranks->byId(RankId::fromString($query->subjectRankId));
        $target = $this->ranks->byId(RankId::fromString($query->targetRankId));

        return $subject->seniority()->isAtLeastAsSeniorAs($target->seniority());
    }
}
