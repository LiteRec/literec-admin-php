<?php

declare(strict_types=1);

namespace App\Administration\Domain\ValueObject;

/**
 * Who performed an Administration write, recorded on every domain event so
 * an audit trail (LRA-273) can attribute a change without a post-commit
 * subscriber reaching into ambient security context — reading token
 * storage there is precisely the ambient dependency the project's
 * ambient-clock-and-randomness PHPStan rule exists to forbid.
 *
 * Each of the three cases carries exactly its own identity and no other:
 * {@see administrator()} has an {@see AdministratorId} and nothing else,
 * {@see signInAccount()} the reverse, {@see system()} neither. This is
 * enforced structurally by each named constructor's signature rather than
 * by a runtime check — there is no way to pass a SignInAccountId into
 * administrator() or an identifier of any kind into system().
 *
 * Created by this slice under the context's merge-first rule: LRA-269
 * specifies this type but merges after LRA-268. Slice (a) of LRA-268
 * builds only signInAccount() (from the authenticated SecurityUser
 * identifier, since no Administrator row exists yet); slice (b) of
 * LRA-268 switches to administrator() once LRA-269 lands, with no change
 * to this type. system() is used by the installer applying LRA-272's
 * default role set.
 */
final readonly class Actor
{
    private function __construct(
        public ActorKind $kind,
        public ?AdministratorId $administratorId,
        public ?SignInAccountId $signInAccountId,
    ) {
    }

    public static function administrator(AdministratorId $id): self
    {
        return new self(ActorKind::Administrator, $id, null);
    }

    public static function signInAccount(SignInAccountId $id): self
    {
        return new self(ActorKind::SignInAccount, null, $id);
    }

    public static function system(): self
    {
        return new self(ActorKind::System, null, null);
    }

    public function equals(self $other): bool
    {
        if ($this->kind !== $other->kind) {
            return false;
        }

        return match ($this->kind) {
            ActorKind::Administrator => $this->administratorId !== null
                && $other->administratorId !== null
                && $this->administratorId->equals($other->administratorId),
            ActorKind::SignInAccount => $this->signInAccountId !== null
                && $other->signInAccountId !== null
                && $this->signInAccountId->equals($other->signInAccountId),
            ActorKind::System => true,
        };
    }
}
