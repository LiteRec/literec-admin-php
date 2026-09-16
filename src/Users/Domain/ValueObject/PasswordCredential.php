<?php

declare(strict_types=1);

namespace App\Users\Domain\ValueObject;

use DateTimeImmutable;

/**
 * Read projection of a User aggregate's password credential (LRA-236):
 * the hash, its lifecycle state, and when the current one-time password
 * (if any) was issued.
 *
 * The three components stay mapped on User as individual scalars — this
 * type only bundles them for callers (e.g.
 * {@see \App\Users\Infrastructure\Security\SecurityUser::from()}) that want
 * them together; it is never itself persisted.
 */
final readonly class PasswordCredential
{
    private function __construct(
        public HashedPassword $hash,
        public PasswordState $state,
        public ?DateTimeImmutable $oneTimePasswordIssuedAt,
    ) {
    }

    public static function established(HashedPassword $hash): self
    {
        return new self($hash, PasswordState::Established, null);
    }

    public static function oneTimeIssued(HashedPassword $hash, DateTimeImmutable $issuedAt): self
    {
        return new self($hash, PasswordState::OneTimeIssued, $issuedAt);
    }

    /**
     * General-purpose projector used by {@see \App\Users\Domain\User::credential()}
     * to reflect the aggregate's current state, including OneTimeConsumed,
     * which has no dedicated named constructor: it is only ever reached by
     * consuming an already-issued credential, never constructed fresh.
     */
    public static function of(
        HashedPassword $hash,
        PasswordState $state,
        ?DateTimeImmutable $oneTimePasswordIssuedAt,
    ): self {
        return new self($hash, $state, $oneTimePasswordIssuedAt);
    }

    public function equals(self $other): bool
    {
        return $this->hash->equals($other->hash)
            && $this->state === $other->state
            && $this->oneTimePasswordIssuedAt == $other->oneTimePasswordIssuedAt;
    }
}
