<?php

declare(strict_types=1);

namespace App\Administration\Domain\ValueObject;

/**
 * Whether an {@see \App\Administration\Domain\Administrator} currently
 * holds their tenure. Derived state — "active exactly when the newest
 * tenure is open" — but persisted as a denormalised column on the
 * administrator row (rather than recomputed from the tenure history on
 * every read) so {@see \App\Administration\Domain\Administrators::forSignInAccount()}
 * and {@see \App\Administration\Domain\Administrators::activeHoldersOfRole()}
 * can filter without loading every tenure.
 */
enum AdministratorStanding: string
{
    case Active = 'ACTIVE';
    case Revoked = 'REVOKED';
}
