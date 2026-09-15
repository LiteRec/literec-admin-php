<?php

declare(strict_types=1);

namespace App\Tests\Unit\Households\Domain\ValueObject;

use App\Households\Domain\ValueObject\AnonymizedProfile;
use App\Households\Domain\ValueObject\Gender;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[Small]
final class AnonymizedProfileTest extends TestCase
{
    #[Test]
    #[TestDox('::placeholder() returns the fixed scrub-set values used everywhere a member is anonymized.')]
    public function placeholder_returns_the_fixed_scrub_set(): void
    {
        $profile = AnonymizedProfile::placeholder();

        self::assertSame('Anonymized', $profile->name->firstName);
        self::assertSame('Member', $profile->name->lastName);
        self::assertSame('1900-01-01', $profile->dateOfBirth->value->format('Y-m-d'));
        self::assertSame(Gender::Unspecified, $profile->gender);
        self::assertSame('Anonymized Household', $profile->householdName->value);
        self::assertSame('Anonymized', $profile->address->street);
        self::assertSame('Anonymized', $profile->address->city);
        self::assertSame('NA', $profile->address->state);
        self::assertSame('N/A', $profile->address->postalCode);
        self::assertSame('ZZ', $profile->address->country);
    }
}
