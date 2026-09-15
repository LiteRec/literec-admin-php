<?php

declare(strict_types=1);

namespace App\Households\Domain\ValueObject;

/**
 * The placeholder identity data a member's profile is replaced with on
 * anonymization (LRA-212). Bundling every scrubbed field into one value
 * object — rather than scattering literal placeholders through
 * {@see \App\Households\Domain\HouseholdMember::anonymize()} — keeps the
 * scrub set defined in exactly one place, so a future ticket adding a new
 * PII field (LRA-205, LRA-207) extends this class instead of hunting
 * through the aggregate.
 *
 * `country` uses `ZZ`, the ISO 3166-1 user-assigned code reserved for
 * exactly this kind of placeholder use: it passes {@see Address}'s
 * two-letter country pattern and — because it matches none of the
 * per-country postal rules in {@see \App\Shared\Domain\ValueObject\PostalCodeRule} —
 * skips per-country postal-code validation entirely.
 */
final readonly class AnonymizedProfile
{
    private function __construct(
        public PersonName $name,
        public DateOfBirth $dateOfBirth,
        public Gender $gender,
        public HouseholdName $householdName,
        public Address $address,
        public ?Salutation $salutation,
        public ?Height $height,
        public ?Weight $weight,
    ) {
    }

    public static function placeholder(): self
    {
        return new self(
            PersonName::of('Anonymized', 'Member'),
            DateOfBirth::fromString('1900-01-01'),
            Gender::Unspecified,
            HouseholdName::of('Anonymized Household'),
            Address::of('Anonymized', null, 'Anonymized', 'NA', 'N/A', 'ZZ'),
            // No placeholder value carries meaning for these (LRA-205):
            // unlike name/date-of-birth/gender/address, the scrub is
            // simply clearing them, so they are always null rather than
            // replaced with a substitute.
            null,
            null,
            null,
        );
    }
}
