<?php

declare(strict_types=1);

namespace App\Households\Domain\ValueObject;

use DateTimeImmutable;

/**
 * Read projection of a {@see \App\Households\Domain\HouseholdMember}'s
 * lifecycle state (LRA-237): whether the member is active, its
 * deactivation record (if any), when it was anonymized (LRA-212, if ever),
 * and its merge record (LRA-208, if any).
 *
 * Pure projection of the four persisted facts — this is a read of
 * already-validated entity state, not re-validation, the same pattern
 * {@see Deactivation}, {@see MemberMerge}, and {@see ProfilePhoto} use.
 * Deliberately carries no transition methods: the transitions
 * (deactivate/reactivate/anonymize/markMergedInto) stay as
 * intention-revealing mutators on the entity itself.
 */
final readonly class MemberLifecycle
{
    public function __construct(
        public bool $isActive,
        public ?Deactivation $deactivation,
        public ?DateTimeImmutable $anonymizedAt,
        public ?MemberMerge $merge,
    ) {
    }

    public function isAnonymized(): bool
    {
        return $this->anonymizedAt !== null;
    }

    public function isMerged(): bool
    {
        return $this->merge !== null;
    }

    public function equals(self $other): bool
    {
        return $this->isActive === $other->isActive
            && self::nullSafeEquals(
                $this->deactivation,
                $other->deactivation,
                static fn(Deactivation $a, Deactivation $b): bool => $a->equals($b),
            )
            && $this->anonymizedAt == $other->anonymizedAt
            && self::nullSafeEquals(
                $this->merge,
                $other->merge,
                static fn(MemberMerge $a, MemberMerge $b): bool => $a->equals($b),
            );
    }

    /**
     * @template T of object
     *
     * @param T|null $a
     * @param T|null $b
     * @param callable(T, T): bool $equals
     */
    private static function nullSafeEquals(?object $a, ?object $b, callable $equals): bool
    {
        if ($a === null && $b === null) {
            return true;
        }

        if ($a === null || $b === null) {
            return false;
        }

        return $equals($a, $b);
    }
}
