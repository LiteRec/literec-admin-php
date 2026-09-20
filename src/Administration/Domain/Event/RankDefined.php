<?php

declare(strict_types=1);

namespace App\Administration\Domain\Event;

use App\Administration\Domain\ValueObject\Actor;
use App\Administration\Domain\ValueObject\AssignedRoles;
use App\Administration\Domain\ValueObject\RankId;
use App\Administration\Domain\ValueObject\RankName;
use App\Administration\Domain\ValueObject\SeniorityLevel;
use DateTimeImmutable;

final readonly class RankDefined
{
    public function __construct(
        public RankId $rankId,
        public RankName $name,
        public SeniorityLevel $seniority,
        public AssignedRoles $roles,
        public Actor $actor,
        public DateTimeImmutable $occurredAt,
    ) {
    }
}
