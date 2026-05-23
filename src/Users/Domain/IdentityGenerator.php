<?php

declare(strict_types=1);

namespace App\Users\Domain;

/**
 * Domain port for generating aggregate identities.
 *
 * Returns a UUID v7 string at this stage of the migration; LRA-15 tightens
 * the return type to a UserId value object once that VO exists.
 */
interface IdentityGenerator
{
    public function nextUserId(): string;
}
