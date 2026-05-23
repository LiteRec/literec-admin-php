<?php

declare(strict_types=1);

namespace App\Users\Domain;

use App\Users\Domain\ValueObject\UserId;

/**
 * Domain port for generating aggregate identities.
 */
interface IdentityGenerator
{
    public function nextUserId(): UserId;
}
