<?php

declare(strict_types=1);

namespace App\Administration\Domain\Event;

use App\Administration\Domain\ValueObject\Actor;
use App\Administration\Domain\ValueObject\AdministratorId;
use DateTimeImmutable;

final readonly class AdministratorRegranted
{
    public function __construct(
        public AdministratorId $administratorId,
        public Actor $grantedBy,
        public DateTimeImmutable $occurredAt,
    ) {
    }
}
