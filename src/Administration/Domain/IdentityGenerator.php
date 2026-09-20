<?php

declare(strict_types=1);

namespace App\Administration\Domain;

use App\Administration\Domain\ValueObject\AdministratorId;
use App\Administration\Domain\ValueObject\AdministratorTenureId;
use App\Administration\Domain\ValueObject\RankId;
use App\Administration\Domain\ValueObject\RoleId;

/**
 * Domain port for generating aggregate identities across the
 * Administration bounded context. One port for the whole context:
 * LRA-268 owns nextRoleId() since it owns the Role aggregate, LRA-267
 * adds nextRankId() for the Rank aggregate, and LRA-269 adds
 * nextAdministratorId() and nextTenureId() for the Administrator
 * aggregate and its child tenures rather than creating a second port.
 */
interface IdentityGenerator
{
    public function nextRoleId(): RoleId;

    public function nextRankId(): RankId;

    public function nextAdministratorId(): AdministratorId;

    public function nextTenureId(): AdministratorTenureId;
}
