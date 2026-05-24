<?php

declare(strict_types=1);

namespace App\Households\Domain\Exception;

use DomainException;

/**
 * Generic catch-all for Households aggregate invariant breaches that do not
 * have a dedicated subtype. Prefer a dedicated exception when the failure
 * mode is named and recurrent; reserve this one for one-off guards.
 *
 * Do not embed PII or other sensitive values (names, emails, phone numbers,
 * raw user input) in the message argument — exception messages can surface
 * in application logs and monitoring. Use a generic, non-reversible
 * description instead.
 */
final class InvariantViolation extends DomainException implements HouseholdsDomainException
{
    public static function with(string $message): self
    {
        return new self($message);
    }
}
