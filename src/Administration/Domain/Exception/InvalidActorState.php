<?php

declare(strict_types=1);

namespace App\Administration\Domain\Exception;

use DomainException;

/**
 * Raised when a command handler reassembles an {@see \App\Administration\Domain\ValueObject\Actor}
 * from a primitive-only command DTO's (ActorKind, ?string identifier) pair
 * and either the kind string does not name an {@see \App\Administration\Domain\ValueObject\ActorKind}
 * case, or the pair is inconsistent with {@see \App\Administration\Domain\ValueObject\Actor}'s
 * per-kind identity invariant: an administrator or sign-in-account kind
 * with no identifier, or a system kind carrying one.
 */
final class InvalidActorState extends DomainException implements AdministrationDomainException
{
    public static function unknownKind(string $kind): self
    {
        return new self(sprintf('"%s" does not name an actor kind.', $kind));
    }

    public static function administratorRequiresIdentifier(): self
    {
        return new self('An administrator actor requires an AdministratorId.');
    }

    public static function signInAccountRequiresIdentifier(): self
    {
        return new self('A sign-in-account actor requires a SignInAccountId.');
    }

    public static function systemCarriesNoIdentifier(): self
    {
        return new self('A system actor must not carry an identifier.');
    }
}
