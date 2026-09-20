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
 *
 * $kind is typed `?ActorKind`, not `ActorKind`, purely to satisfy
 * Doctrine's mapping for {@see \App\Administration\Domain\AdministratorTenure::$revokedBy}
 * (LRA-269): that field embeds this class but is genuinely absent while
 * a tenure is open. PHPStan's Doctrine extension checks property
 * nullability against the embeddable's mapped column nullability, which
 * must allow null for $kind to double as the optional revokedBy —
 * hence the widened type here rather than a mapping mismatch or a
 * suppressed error.
 *
 * This does mean a *hydrated* Actor can transiently have every field
 * null: Doctrine ORM 3.7 does not leave the enclosing property
 * (revokedBy) null when every embedded column is NULL — it constructs
 * an Actor with every field null instead (confirmed against a live
 * reload; an earlier revision of this docblock assumed the opposite).
 * {@see \App\Administration\Domain\AdministratorTenure::normalizeAfterLoad()}
 * is the fix: a Doctrine postLoad callback that collapses that
 * all-null Actor back to a genuine null, so revokedBy() upholds the
 * open-tenure invariant regardless of how the row was hydrated. Actor
 * built through one of the three named constructors below never holds
 * a null $kind.
 */
final readonly class Actor
{
    private function __construct(
        public ?ActorKind $kind,
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
            // Unreachable on any real Actor instance — see this class's
            // $kind docblock — but required for match() exhaustiveness
            // now that $kind is nullable for Doctrine's sake.
            null => true,
        };
    }
}
