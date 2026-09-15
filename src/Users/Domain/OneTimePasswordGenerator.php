<?php

declare(strict_types=1);

namespace App\Users\Domain;

use App\Users\Domain\ValueObject\OneTimePassword;

/**
 * Domain port for generating a fresh one-time login credential (LRA-213).
 *
 * Kept as a port so the Domain and Application layers never import the
 * random-string generation library the Infrastructure adapter uses.
 */
interface OneTimePasswordGenerator
{
    public function generate(): OneTimePassword;
}
