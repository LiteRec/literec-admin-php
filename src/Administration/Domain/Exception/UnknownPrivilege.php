<?php

declare(strict_types=1);

namespace App\Administration\Domain\Exception;

use DomainException;

/**
 * Raised whenever a privilege name is checked and does not resolve to a
 * {@see \App\Administration\Domain\Privilege} case — either the name was
 * never a privilege, or a stored grant still names a case that has since
 * been removed from the catalogue.
 *
 * The {@see \App\Administration\Domain\PrivilegeLookup} port is documented
 * to throw this and cannot return null, so no caller can turn an
 * unrecognised name into a grant: an unknown privilege denies by
 * construction rather than by a per-call-site decision.
 */
final class UnknownPrivilege extends DomainException implements AdministrationDomainException
{
    public static function named(string $name): self
    {
        return new self(sprintf(
            '"%s" does not name a privilege in the catalogue.',
            $name,
        ));
    }
}
