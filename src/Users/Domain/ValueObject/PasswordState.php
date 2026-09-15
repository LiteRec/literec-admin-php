<?php

declare(strict_types=1);

namespace App\Users\Domain\ValueObject;

/**
 * Closed set of credential lifecycle states for a User aggregate's password.
 *
 * Established is the steady state every account starts and ends in.
 * OneTimeIssued marks a staff-issued temporary credential that has not yet
 * been used; OneTimeConsumed marks one that has been used exactly once and
 * can never authenticate again (LRA-213).
 */
enum PasswordState: string
{
    case Established = 'established';
    case OneTimeIssued = 'one_time_issued';
    case OneTimeConsumed = 'one_time_consumed';

    public function mustBeReplaced(): bool
    {
        return $this !== self::Established;
    }
}
