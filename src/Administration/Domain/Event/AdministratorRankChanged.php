<?php

declare(strict_types=1);

namespace App\Administration\Domain\Event;

use App\Administration\Domain\ValueObject\Actor;
use App\Administration\Domain\ValueObject\AdministratorId;
use App\Administration\Domain\ValueObject\RankId;
use DateTimeImmutable;

final readonly class AdministratorRankChanged
{
    public function __construct(
        public AdministratorId $administratorId,
        public RankId $previousRankId,
        public RankId $newRankId,
        public Actor $changedBy,
        public DateTimeImmutable $occurredAt,
    ) {
    }
}
