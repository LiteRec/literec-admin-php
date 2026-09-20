<?php

declare(strict_types=1);

namespace App\Administration\Domain\Event;

use App\Administration\Domain\ValueObject\Actor;
use App\Administration\Domain\ValueObject\AdministratorId;
use App\Administration\Domain\ValueObject\RankId;
use App\Administration\Domain\ValueObject\SignInAccountId;
use DateTimeImmutable;

final readonly class AdministratorGranted
{
    public function __construct(
        public AdministratorId $administratorId,
        public SignInAccountId $signInAccountId,
        public RankId $rankId,
        public Actor $grantedBy,
        public DateTimeImmutable $occurredAt,
    ) {
    }
}
