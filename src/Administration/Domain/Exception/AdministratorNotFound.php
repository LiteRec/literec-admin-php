<?php

declare(strict_types=1);

namespace App\Administration\Domain\Exception;

use App\Administration\Domain\ValueObject\AdministratorId;
use App\Administration\Domain\ValueObject\SignInAccountId;
use DomainException;

final class AdministratorNotFound extends DomainException implements AdministrationDomainException
{
    public static function withId(AdministratorId $id): self
    {
        return new self(sprintf('Administrator %s was not found.', $id->value));
    }

    public static function forSignInAccount(SignInAccountId $id): self
    {
        return new self(sprintf('No administrator record exists for sign-in account %s.', $id->value));
    }
}
