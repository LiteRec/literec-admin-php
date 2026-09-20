<?php

declare(strict_types=1);

namespace App\Administration\Domain;

use App\Administration\Domain\ValueObject\RoleId;

/**
 * Domain port for generating aggregate identities across the
 * Administration bounded context. One port for the whole context: this
 * ticket owns nextRoleId() since it owns the Role aggregate; later
 * tickets (LRA-267's Rank, LRA-269's Administrator) add their own
 * method here rather than creating a second port.
 */
interface IdentityGenerator
{
    public function nextRoleId(): RoleId;
}
