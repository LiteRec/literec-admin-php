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

/**
 * Registers the single-member "Smith Family" {@see Household} fixture
 * shared by HouseholdTest and MemberInHouseholdTest (LRA-238) so the two
 * classes' household-level and per-member test suites build on the exact
 * same base scenario without duplicating the Household::register() call.
 *
 * Requires the composing class to declare a `private MockClock $clock`
 * property (both classes already do, for their own event-timestamp
 * assertions).
 */
trait RegistersSmithHousehold
{
    private function registerSmithHousehold(string $householdId, string $primaryMemberId): Household
    {
        return Household::register(
            HouseholdId::fromString($householdId),
            HouseholdName::of('Smith Family'),
            Address::of('123 Main St', 'Apt 4B', 'Springfield', 'IL', '62701', 'US'),
            MemberId::fromString($primaryMemberId),
            MemberCode::of('M0001'),
            MemberProfile::of(
                PersonName::of('Alice', 'Smith'),
                DateOfBirth::of(new DateTimeImmutable('1990-01-01'), $this->clock),
                Gender::Female,
            ),
            MemberContact::of(EmailAddress::of('alice@example.com'), PhoneNumber::of('5550001')),
            ResidencyStatus::Resident,
            $this->clock,
        );
    }
}
