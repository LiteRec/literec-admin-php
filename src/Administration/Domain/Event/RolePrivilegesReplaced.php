<?php

declare(strict_types=1);

namespace App\Administration\Domain\Event;

use App\Administration\Domain\ValueObject\Actor;
use App\Administration\Domain\ValueObject\PrivilegeSet;
use App\Administration\Domain\ValueObject\RoleId;
use DateTimeImmutable;

/**
 * Carries both the before and after {@see PrivilegeSet} so LRA-273 can
 * diff a bundle save into per-privilege audit entries; this single event
 * instance is the correlation unit for that diff, so no separate
 * correlation identifier is needed.
 */
final readonly class RolePrivilegesReplaced
{
    public function __construct(
        public RoleId $roleId,
        public PrivilegeSet $before,
        public PrivilegeSet $after,
        public Actor $actor,
        public DateTimeImmutable $occurredAt,
    ) {
    }
}
