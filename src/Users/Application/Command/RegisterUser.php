<?php

declare(strict_types=1);

namespace App\Users\Application\Command;

/**
 * Primitive-only command DTO for the RegisterUser use case.
 *
 * Holds strings + a list of role strings; value-object construction
 * happens inside the handler so invalid input surfaces as a named
 * domain exception rather than a constructor TypeError.
 */
final readonly class RegisterUser
{
    /**
     * @param list<string> $roles Role enum string values, e.g. ['ROLE_ADMIN'].
     */
    public function __construct(
        public string $username,
        public string $plaintextPassword,
        public array $roles = [],
    ) {
    }
}
