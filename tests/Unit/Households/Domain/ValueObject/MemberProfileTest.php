<?php

declare(strict_types=1);

namespace App\Tests\Unit\Households\Domain\ValueObject;

use App\Households\Domain\ValueObject\DateOfBirth;
use App\Households\Domain\ValueObject\Gender;
use App\Households\Domain\ValueObject\Height;
use App\Households\Domain\ValueObject\MemberProfile;
use App\Households\Domain\ValueObject\PersonName;
use App\Households\Domain\ValueObject\Salutation;
use App\Households\Domain\ValueObject\Weight;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[Small]
final class MemberProfileTest extends TestCase
{
    #[Test]
    #[TestDox('::of() exposes name/dateOfBirth/gender, with salutation/height/weight defaulting to null.')]
    public function of_exposes_every_field(): void
    {
        $name = PersonName::of('Alice', 'Smith');
        $dateOfBirth = DateOfBirth::fromString('1990-01-01');

        $profile = MemberProfile::of($name, $dateOfBirth, Gender::Female);

        self::assertTrue($profile->name->equals($name));
        self::assertTrue($profile->dateOfBirth->equals($dateOfBirth));
        self::assertSame(Gender::Female, $profile->gender);
        self::assertNull($profile->salutation);
        self::assertNull($profile->height);
        self::assertNull($profile->weight);
    }

    #[Test]
    #[TestDox('::of() accepts the three independently-optional salutation/height/weight fields.')]
    public function of_accepts_optional_measurement_fields(): void
    {
        $profile = MemberProfile::of(
            PersonName::of('Alice', 'Smith'),
            DateOfBirth::fromString('1990-01-01'),
            Gender::Female,
            Salutation::Ms,
            Height::ofInches(65),
            Weight::ofPounds(140),
        );

        self::assertSame(Salutation::Ms, $profile->salutation);
        self::assertSame(65, $profile->height?->inches);
        self::assertSame(140, $profile->weight?->pounds);
    }

    #[Test]
    #[TestDox('::equals() is true for the same name, date of birth, gender, and null measurements.')]
    public function equals_true_for_same_values(): void
    {
        $a = MemberProfile::of(PersonName::of('Alice', 'Smith'), DateOfBirth::fromString('1990-01-01'), Gender::Female);
        $b = MemberProfile::of(PersonName::of('Alice', 'Smith'), DateOfBirth::fromString('1990-01-01'), Gender::Female);

        self::assertTrue($a->equals($b));
    }

    #[Test]
    #[TestDox('::equals() is false when the name differs.')]
    public function equals_false_for_different_name(): void
    {
        $dateOfBirth = DateOfBirth::fromString('1990-01-01');
        $a = MemberProfile::of(PersonName::of('Alice', 'Smith'), $dateOfBirth, Gender::Female);
        $b = MemberProfile::of(PersonName::of('Alicia', 'Smith'), $dateOfBirth, Gender::Female);

        self::assertFalse($a->equals($b));
    }

    #[Test]
    #[TestDox('::equals() is false when the date of birth differs.')]
    public function equals_false_for_different_date_of_birth(): void
    {
        $a = MemberProfile::of(PersonName::of('Alice', 'Smith'), DateOfBirth::fromString('1990-01-01'), Gender::Female);
        $b = MemberProfile::of(PersonName::of('Alice', 'Smith'), DateOfBirth::fromString('1991-01-01'), Gender::Female);

        self::assertFalse($a->equals($b));
    }

    #[Test]
    #[TestDox('::equals() is false when the gender differs.')]
    public function equals_false_for_different_gender(): void
    {
        $a = MemberProfile::of(PersonName::of('Alice', 'Smith'), DateOfBirth::fromString('1990-01-01'), Gender::Female);
        $b = MemberProfile::of(PersonName::of('Alice', 'Smith'), DateOfBirth::fromString('1990-01-01'), Gender::Male);

        self::assertFalse($a->equals($b));
    }

    #[Test]
    #[TestDox('::equals() is false when the salutation differs.')]
    public function equals_false_for_different_salutation(): void
    {
        $a = self::baseProfile(salutation: Salutation::Ms);
        $b = self::baseProfile(salutation: Salutation::Mrs);

        self::assertFalse($a->equals($b));
    }

    #[Test]
    #[TestDox('::equals() null-safely compares height: true when both null, false when only one side is null.')]
    public function equals_null_safely_compares_height(): void
    {
        $bothNull = self::baseProfile();
        $otherBothNull = self::baseProfile();
        $oneHeight = self::baseProfile(height: Height::ofInches(65));

        self::assertTrue($bothNull->equals($otherBothNull));
        self::assertFalse($bothNull->equals($oneHeight));
        self::assertFalse($oneHeight->equals($bothNull));
    }

    #[Test]
    #[TestDox('::equals() is true for equal height values and false for differing ones.')]
    public function equals_compares_height_by_value(): void
    {
        $a = self::baseProfile(height: Height::ofInches(65));
        $b = self::baseProfile(height: Height::ofInches(65));
        $c = self::baseProfile(height: Height::ofInches(70));

        self::assertTrue($a->equals($b));
        self::assertFalse($a->equals($c));
    }

    #[Test]
    #[TestDox('::equals() null-safely compares weight: true when both null, false when only one side is null.')]
    public function equals_null_safely_compares_weight(): void
    {
        $bothNull = self::baseProfile();
        $otherBothNull = self::baseProfile();
        $oneWeight = self::baseProfile(weight: Weight::ofPounds(140));

        self::assertTrue($bothNull->equals($otherBothNull));
        self::assertFalse($bothNull->equals($oneWeight));
        self::assertFalse($oneWeight->equals($bothNull));
    }

    #[Test]
    #[TestDox('::equals() is true for equal weight values and false for differing ones.')]
    public function equals_compares_weight_by_value(): void
    {
        $a = self::baseProfile(weight: Weight::ofPounds(140));
        $b = self::baseProfile(weight: Weight::ofPounds(140));
        $c = self::baseProfile(weight: Weight::ofPounds(150));

        self::assertTrue($a->equals($b));
        self::assertFalse($a->equals($c));
    }

    private static function baseProfile(
        ?Salutation $salutation = null,
        ?Height $height = null,
        ?Weight $weight = null,
    ): MemberProfile {
        return MemberProfile::of(
            PersonName::of('Alice', 'Smith'),
            DateOfBirth::fromString('1990-01-01'),
            Gender::Female,
            $salutation,
            $height,
            $weight,
        );
    }
}
