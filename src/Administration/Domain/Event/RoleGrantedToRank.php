<?php

declare(strict_types=1);

namespace App\Administration\Domain\Event;

use App\Administration\Domain\ValueObject\Actor;
use App\Administration\Domain\ValueObject\RankId;
use App\Administration\Domain\ValueObject\RoleId;
use DateTimeImmutable;

/**
 * One of the two events (with {@see RoleRevokedFromRank}) LRA-273 audits
 * for rank-to-role changes. Names fixed by LRA-267.
 */
final readonly class RoleGrantedToRank
{
    public function __construct(
        public RankId $rankId,
        public RoleId $roleId,
        public Actor $actor,
        public DateTimeImmutable $occurredAt,
    ) {
    }
}
