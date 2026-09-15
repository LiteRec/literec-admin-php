<?php

declare(strict_types=1);

namespace App\Tests\Support\Trait;

use App\Households\Domain\Household;
use App\Households\Domain\Households;
use App\Households\Domain\ValueObject\Address;
use App\Households\Domain\ValueObject\DateOfBirth;
use App\Households\Domain\ValueObject\Gender;
use App\Households\Domain\ValueObject\HouseholdId;
use App\Households\Domain\ValueObject\HouseholdName;
use App\Households\Domain\ValueObject\MemberCode;
use App\Households\Domain\ValueObject\MemberId;
use App\Households\Domain\ValueObject\PersonName;
use App\Households\Domain\ValueObject\ResidencyStatus;
use App\Shared\Domain\ValueObject\EmailAddress;
use DateTimeImmutable;
use Psr\Clock\ClockInterface;

/**
 * Saves the single-member "Alice Smith" household fixture, through the
 * real container-bound repository, that the Households functional tests
 * seed their Profile-card scenarios against. Extracted for LRA-207 so
 * MemberPhotoControllerTest and MemberProfileCardTest do not duplicate
 * this construction verbatim (SonarCloud new-code duplication threshold).
 */
trait SeedsSmithHouseholdForUi
{
    private function seedSmithHousehold(
        Households $repo,
        HouseholdId $householdId,
        MemberId $primaryId,
        MemberCode $primaryCode,
        string $dobIso,
        ClockInterface $clock,
    ): Household {
        $household = Household::register(
            $householdId,
            HouseholdName::of('Smith Family'),
            Address::of('100 Main St', 'Apt 2B', 'Seattle', 'WA', '98101', 'US'),
            $primaryId,
            $primaryCode,
            PersonName::of('Alice', 'Smith'),
            DateOfBirth::of(new DateTimeImmutable($dobIso), $clock),
            Gender::Female,
            EmailAddress::of('alice@example.com'),
            null,
            ResidencyStatus::Resident,
            $clock,
        );

        $repo->save($household);

        return $household;
    }
}
