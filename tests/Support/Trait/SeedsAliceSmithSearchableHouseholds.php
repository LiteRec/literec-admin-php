<?php

declare(strict_types=1);

namespace App\Tests\Support\Trait;

use App\Households\Domain\Household;
use App\Households\Domain\ValueObject\Address;
use App\Households\Domain\ValueObject\DateOfBirth;
use App\Households\Domain\ValueObject\Gender;
use App\Households\Domain\ValueObject\HouseholdId;
use App\Households\Domain\ValueObject\HouseholdName;
use App\Households\Domain\ValueObject\MemberCode;
use App\Households\Domain\ValueObject\MemberContact;
use App\Households\Domain\ValueObject\MemberId;
use App\Households\Domain\ValueObject\MemberProfile;
use App\Households\Domain\ValueObject\PersonName;
use App\Households\Domain\ValueObject\ResidencyStatus;
use App\Shared\Domain\ValueObject\EmailAddress;
use App\Shared\Domain\ValueObject\PhoneNumber;
use DateTimeImmutable;
use Psr\Clock\ClockInterface;

/**
 * Builds the "Household A" (Alice Smith + Bob Brown) and "Household B"
 * (Carl Smith) fixtures shared by {@see \App\Tests\Functional\Households\SearchMembersControllerTest}
 * and {@see \App\Tests\Functional\Households\MemberLookupControllerTest} —
 * the two tests deliberately mirror this seeding so the View Members list
 * (LRA-39) and the reusable Member Lookup endpoint (LRA-46) stay
 * observable side-by-side. Extracted for LRA-237 so the two test classes
 * do not carry byte-identical `Household::register()`/`addMember()` calls
 * (SonarCloud new-code duplication threshold).
 */
trait SeedsAliceSmithSearchableHouseholds
{
    private function buildHouseholdA(
        HouseholdId $householdId,
        MemberId $primaryId,
        MemberCode $primaryCode,
        MemberId $secondId,
        MemberCode $secondCode,
        ClockInterface $clock,
    ): Household {
        $household = Household::register(
            $householdId,
            HouseholdName::of('Smith Family'),
            Address::of('100 Main St', 'Apt 2B', 'Seattle', 'WA', '98101', 'US'),
            $primaryId,
            $primaryCode,
            MemberProfile::of(
                PersonName::of('Alice', 'Smith'),
                DateOfBirth::of(new DateTimeImmutable('1990-01-01'), $clock),
                Gender::Female,
            ),
            MemberContact::of(EmailAddress::of('alice@example.com'), null),
            ResidencyStatus::Resident,
            $clock,
        );

        $household->addMember(
            $secondId,
            $secondCode,
            MemberProfile::of(
                PersonName::of('Bob', 'Brown'),
                DateOfBirth::of(new DateTimeImmutable('1992-03-04'), $clock),
                Gender::Male,
            ),
            MemberContact::of(null, PhoneNumber::of('5550002')),
            ResidencyStatus::NonResident,
            false,
            $clock,
        );

        return $household;
    }

    private function buildHouseholdB(
        HouseholdId $householdId,
        MemberId $primaryId,
        MemberCode $primaryCode,
        ClockInterface $clock,
    ): Household {
        return Household::register(
            $householdId,
            HouseholdName::of('Smith-Lopez Household'),
            Address::of('200 Oak Ave', null, 'Portland', 'OR', '97201', 'US'),
            $primaryId,
            $primaryCode,
            MemberProfile::of(
                PersonName::of('Carl', 'Smith'),
                DateOfBirth::of(new DateTimeImmutable('1985-11-30'), $clock),
                Gender::Male,
            ),
            MemberContact::of(EmailAddress::of('carl@example.com'), PhoneNumber::of('5550100')),
            ResidencyStatus::Member,
            $clock,
        );
    }
}
