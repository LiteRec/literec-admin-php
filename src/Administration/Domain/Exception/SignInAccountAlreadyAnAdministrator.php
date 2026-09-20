<?php

declare(strict_types=1);

namespace App\Administration\Domain\Exception;

use App\Administration\Domain\ValueObject\SignInAccountId;
use DomainException;

/**
 * Raised when {@see \App\Administration\Domain\Administrators::add()} races
 * (or repeats) a grant for a sign-in account that already has an
 * administrator row — caught via the unique constraint on
 * administration_administrators.sign_in_account_id, since a sign-in
 * account may hold at most one administrator record for its entire
 * lifetime (see that column's docblock).
 */
final class SignInAccountAlreadyAnAdministrator extends DomainException implements AdministrationDomainException
{
    public static function for(SignInAccountId $id): self
    {
        return new self(sprintf('Sign-in account %s already has an administrator record.', $id->value));
    }
}
