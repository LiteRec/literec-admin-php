<?php

declare(strict_types=1);

namespace App\Households\Domain\ValueObject;

use App\Households\Domain\NullSafeEquality;

/**
 * Read projection of a {@see \App\Households\Domain\HouseholdMember}'s
 * identity/demographic fields (LRA-237): name, date of birth, gender, and
 * the three independently-optional LRA-205 fields (salutation, height,
 * weight).
 *
 * The six components stay mapped on HouseholdMember as individual scalars
 * (or, for name, the existing PersonName embeddable) — this type only
 * bundles them for callers that want the whole profile together, replacing
 * six loose getters with one, and {@see \App\Households\Domain\HouseholdMember::updateProfile()}
 * writes all six scalars from a single instance instead of five
 * field-level mutators.
 */
final readonly class MemberProfile
{
    use NullSafeEquality;

    private function __construct(
        public PersonName $name,
        public DateOfBirth $dateOfBirth,
        public Gender $gender,
        public ?Salutation $salutation,
        public ?Height $height,
        public ?Weight $weight,
    ) {
    }

    public static function of(
        PersonName $name,
        DateOfBirth $dateOfBirth,
        Gender $gender,
        ?Salutation $salutation = null,
        ?Height $height = null,
        ?Weight $weight = null,
    ): self {
        return new self($name, $dateOfBirth, $gender, $salutation, $height, $weight);
    }

    public function equals(self $other): bool
    {
        return $this->name->equals($other->name)
            && $this->dateOfBirth->equals($other->dateOfBirth)
            && $this->gender === $other->gender
            && $this->salutation === $other->salutation
            && self::nullSafeEquals(
                $this->height,
                $other->height,
                static fn(Height $a, Height $b): bool => $a->equals($b),
            )
            && self::nullSafeEquals(
                $this->weight,
                $other->weight,
                static fn(Weight $a, Weight $b): bool => $a->equals($b),
            );
    }
}
