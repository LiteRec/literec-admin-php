<?php

declare(strict_types=1);

namespace App\Users\Domain\ValueObject;

/**
 * Closed set of role values granted to a User aggregate.
 *
 * Backed by the same string values Symfony Security expects so the
 * SecurityUser adapter (LRA-21) can project `$role->value` straight into
 * `getRoles()`.
 */
enum Role: string
{
    case User = 'ROLE_USER';
    case Admin = 'ROLE_ADMIN';
}
