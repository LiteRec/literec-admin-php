<?php

declare(strict_types=1);

namespace App\Administration\Domain;

use App\Administration\Domain\ValueObject\RankId;
use App\Administration\Domain\ValueObject\RoleId;

/**
 * Domain port for generating aggregate identities across the
 * Administration bounded context. One port for the whole context:
 * LRA-268 owns nextRoleId() since it owns the Role aggregate, LRA-267
 * adds nextRankId() for the Rank aggregate, and LRA-269 adds its own
 * methods for Administrator and Tenure rather than creating a second
 * port.
 */
interface IdentityGenerator
{
    public function nextRoleId(): RoleId;

    public function nextRankId(): RankId;
}
