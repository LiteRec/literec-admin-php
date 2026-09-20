<?php

declare(strict_types=1);

namespace App\Administration\Domain\Event;

use App\Administration\Domain\ValueObject\Actor;
use App\Administration\Domain\ValueObject\RankId;
use DateTimeImmutable;

final readonly class RankRetired
{
    public function __construct(
        public RankId $rankId,
        public Actor $actor,
        public DateTimeImmutable $occurredAt,
    ) {
    }
}
