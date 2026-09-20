<?php

declare(strict_types=1);

namespace App\Administration\Domain\Exception;

use App\Administration\Domain\ValueObject\AdministratorId;
use DomainException;

/**
 * Raised when {@see \App\Administration\Domain\Administrators::add()}
 * is given an {@see AdministratorId} that already has a row — caught via
 * the primary-key unique constraint on the Doctrine adapter, and guarded
 * directly on the in-memory adapter so the two never drift (an
 * AdministratorId collision is not expected in ordinary operation, since
 * {@see \App\Administration\Domain\IdentityGenerator} produces UUID v7s,
 * but a caller that mistakenly re-adds an existing aggregate must be
 * told so rather than have its tenure history silently overwritten).
 */
final class AdministratorAlreadyExists extends DomainException implements AdministrationDomainException
{
    public static function withId(AdministratorId $id): self
    {
        return new self(sprintf('Administrator %s already exists.', $id->value));
    }
}
