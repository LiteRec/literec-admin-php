<?php

declare(strict_types=1);

namespace App\Administration\Domain\Event;

use App\Administration\Domain\ValueObject\Actor;
use App\Administration\Domain\ValueObject\AdministratorId;
use App\Administration\Domain\ValueObject\RevocationReason;
use DateTimeImmutable;

final readonly class AdministratorRevoked
{
    public function __construct(
        public AdministratorId $administratorId,
        public RevocationReason $reason,
        public Actor $revokedBy,
        public DateTimeImmutable $occurredAt,
    ) {
    }
}
