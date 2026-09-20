<?php

declare(strict_types=1);

namespace App\Administration\Domain\Event;

use App\Administration\Domain\ValueObject\Actor;
use App\Administration\Domain\ValueObject\RankId;
use App\Administration\Domain\ValueObject\SeniorityLevel;
use DateTimeImmutable;

final readonly class RankSeniorityChanged
{
    public function __construct(
        public RankId $rankId,
        public SeniorityLevel $seniority,
        public Actor $actor,
        public DateTimeImmutable $occurredAt,
    ) {
    }
}
