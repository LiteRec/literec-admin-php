<?php

declare(strict_types=1);

namespace App\Administration\Domain\Exception;

use DomainException;

/**
 * Raised when a {@see \App\Administration\Domain\PrivilegeDefinition} is
 * constructed with a display name, description, or ordering that violates
 * its invariants.
 */
final class InvalidPrivilegeDefinition extends DomainException implements AdministrationDomainException
{
    public static function emptyDisplayName(): self
    {
        return new self('A privilege definition must carry a non-empty display name.');
    }

    public static function emptyDescription(): self
    {
        return new self('A privilege definition must carry a non-empty description.');
    }

    public static function negativeOrder(int $order): self
    {
        return new self(sprintf(
            'A privilege definition\'s order must not be negative, got %d.',
            $order,
        ));
    }
}
