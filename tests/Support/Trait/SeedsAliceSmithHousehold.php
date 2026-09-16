<?php

declare(strict_types=1);

namespace App\Tests\Support\Trait;

use App\Households\Domain\Household;
use App\Households\Domain\HouseholdMember;
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
use DateTimeImmutable;
use Psr\Clock\ClockInterface;

/**
 * Builds the single-member "Alice Smith" household fixture used by the
 * Households unit-level command-handler tests, and finds a member within
 * it by id. Extracted for LRA-207 so AttachMemberPhotoHandlerTest and
 * RemoveMemberPhotoHandlerTest do not duplicate this construction verbatim
 * (SonarCloud new-code duplication threshold).
 */
trait SeedsAliceSmithHousehold
{
    private function seedAliceSmithHousehold(
        HouseholdId $householdId,
        MemberId $primaryId,
        MemberCode $primaryCode,
        ClockInterface $clock,
    ): Household {
        return Household::register(
            $householdId,
            HouseholdName::of('Smith Family'),
            Address::of('100 Main St', null, 'Seattle', 'WA', '98101', 'US'),
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
    }

    private function memberById(Household $household, string $memberId): HouseholdMember
    {
        $needle = MemberId::fromString($memberId);
        foreach ($household->members() as $member) {
            if ($member->id()->equals($needle)) {
                return $member;
            }
        }

        self::fail(sprintf('Member %s not found in household.', $memberId));
    }
}
