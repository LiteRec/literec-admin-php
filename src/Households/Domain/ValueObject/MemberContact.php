<?php

declare(strict_types=1);

namespace App\Households\Domain\ValueObject;

use App\Shared\Domain\ValueObject\EmailAddress;
use App\Shared\Domain\ValueObject\PhoneNumber;

/**
 * Read projection of a {@see \App\Households\Domain\HouseholdMember}'s
 * contact details (LRA-237): email and phone, each independently optional.
 *
 * The two components stay mapped on HouseholdMember as individual
 * scalars — this type only bundles them for callers that want the pair
 * together, replacing the loose email()/phone() getters with one, and
 * {@see \App\Households\Domain\HouseholdMember::updateContact()} writes
 * both scalars from a single instance.
 */
final readonly class MemberContact
{
    private function __construct(
        public ?EmailAddress $email,
        public ?PhoneNumber $phone,
    ) {
    }

    public static function of(?EmailAddress $email, ?PhoneNumber $phone): self
    {
        return new self($email, $phone);
    }

    public static function none(): self
    {
        return new self(null, null);
    }

    /**
     * The LRA-208 merge gap-fill rule: keeps this instance's existing
     * email/phone, taking $email/$phone only where the corresponding
     * field on this instance is blank.
     */
    public function filledFrom(?EmailAddress $email, ?PhoneNumber $phone): self
    {
        return self::of($this->email ?? $email, $this->phone ?? $phone);
    }

    public function equals(self $other): bool
    {
        return self::nullSafeEquals(
            $this->email,
            $other->email,
            static fn(EmailAddress $a, EmailAddress $b): bool => $a->equals($b),
        ) && self::nullSafeEquals(
            $this->phone,
            $other->phone,
            static fn(PhoneNumber $a, PhoneNumber $b): bool => $a->equals($b),
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
