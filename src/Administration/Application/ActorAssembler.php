<?php

declare(strict_types=1);

namespace App\Administration\Application;

use App\Administration\Domain\Exception\InvalidActorState;
use App\Administration\Domain\ValueObject\Actor;
use App\Administration\Domain\ValueObject\ActorKind;
use App\Administration\Domain\ValueObject\AdministratorId;
use App\Administration\Domain\ValueObject\SignInAccountId;

/**
 * Reassembles the {@see Actor} that every Administration command handler
 * stamps onto its aggregate's events, from the (ActorKind, ?string
 * identifier) primitive pair every command DTO carries at its trailing
 * two constructor arguments.
 *
 * Extracted once and shared by every Role write handler rather than
 * repeating the same three-way match seven times — an inconsistent pair
 * (a system kind carrying an identifier, or an administrator/sign-in
 * kind without one) fails here, in the domain, rather than silently
 * recording the wrong actor.
 */
final class ActorAssembler
{
    /**
     * @throws InvalidActorState when $actorKind does not name an
     *         ActorKind case, or when the (kind, identifier) pair is
     *         inconsistent with Actor's per-kind identity invariant.
     */
    public function fromPrimitives(string $actorKind, ?string $actorId): Actor
    {
        $kind = ActorKind::tryFrom($actorKind) ?? throw InvalidActorState::unknownKind($actorKind);

        return match ($kind) {
            ActorKind::Administrator => Actor::administrator(AdministratorId::fromString(
                $actorId ?? throw InvalidActorState::administratorRequiresIdentifier(),
            )),
            ActorKind::SignInAccount => Actor::signInAccount(SignInAccountId::fromString(
                $actorId ?? throw InvalidActorState::signInAccountRequiresIdentifier(),
            )),
            ActorKind::System => match ($actorId) {
                null => Actor::system(),
                default => throw InvalidActorState::systemCarriesNoIdentifier(),
            },
        };
    }
}
