<?php

declare(strict_types=1);

namespace App\Tests\Unit\Households\Domain\ValueObject;

use App\Households\Domain\ValueObject\HouseholdId;
use App\Households\Domain\ValueObject\HouseholdLink;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[Small]
final class HouseholdLinkTest extends TestCase
{
    private const string TARGET_HOUSEHOLD_ID = '019571bf-5d51-7000-b500-000000000005';

    #[Test]
    #[TestDox('::of() exposes the household id and linked-at timestamp as public properties.')]
    public function of_exposes_household_id_and_linked_at(): void
    {
        $householdId = HouseholdId::fromString(self::TARGET_HOUSEHOLD_ID);
        $linkedAt = new DateTimeImmutable('2026-01-01 12:00:00');

        $link = HouseholdLink::of($householdId, $linkedAt);

        self::assertTrue($link->householdId->equals($householdId));
        self::assertEquals($linkedAt, $link->linkedAt);
    }

    #[Test]
    #[TestDox('::equals() is true for the same household id and timestamp.')]
    public function equals_true_for_same_values(): void
    {
        $householdId = HouseholdId::fromString(self::TARGET_HOUSEHOLD_ID);
        $linkedAt = new DateTimeImmutable('2026-01-01 12:00:00');

        $a = HouseholdLink::of($householdId, $linkedAt);
        $b = HouseholdLink::of($householdId, $linkedAt);

        self::assertTrue($a->equals($b));
    }

    #[Test]
    #[TestDox('::equals() is false when the household id differs.')]
    public function equals_false_for_different_household_id(): void
    {
        $linkedAt = new DateTimeImmutable('2026-01-01 12:00:00');
        $a = HouseholdLink::of(HouseholdId::fromString(self::TARGET_HOUSEHOLD_ID), $linkedAt);
        $b = HouseholdLink::of(HouseholdId::fromString('019571bf-5d51-7000-b500-000000000006'), $linkedAt);

        self::assertFalse($a->equals($b));
    }

    #[Test]
    #[TestDox('::equals() is false when the linked-at timestamp differs.')]
    public function equals_false_for_different_linked_at(): void
    {
        $householdId = HouseholdId::fromString(self::TARGET_HOUSEHOLD_ID);
        $a = HouseholdLink::of($householdId, new DateTimeImmutable('2026-01-01 12:00:00'));
        $b = HouseholdLink::of($householdId, new DateTimeImmutable('2026-01-02 12:00:00'));

        self::assertFalse($a->equals($b));
    }
}
