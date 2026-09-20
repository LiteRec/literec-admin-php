<?php

declare(strict_types=1);

namespace App\Administration\Domain\Event;

use App\Administration\Domain\ValueObject\Actor;
use App\Administration\Domain\ValueObject\RankId;
use App\Administration\Domain\ValueObject\RankName;
use DateTimeImmutable;

final readonly class RankRenamed
{
    public function __construct(
        public RankId $rankId,
        public RankName $name,
        public Actor $actor,
        public DateTimeImmutable $occurredAt,
    ) {
    }
}
