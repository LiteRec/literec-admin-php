<?php

declare(strict_types=1);

namespace App\Administration\Domain\Event;

use App\Administration\Domain\ValueObject\Actor;
use App\Administration\Domain\ValueObject\AdministratorId;
use App\Administration\Domain\ValueObject\RoleId;
use DateTimeImmutable;

final readonly class RoleAssignedToAdministrator
{
    public function __construct(
        public AdministratorId $administratorId,
        public RoleId $roleId,
        public Actor $assignedBy,
        public DateTimeImmutable $occurredAt,
    ) {
    }
}
