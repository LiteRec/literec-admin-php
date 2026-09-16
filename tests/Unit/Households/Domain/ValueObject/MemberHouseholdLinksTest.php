<?php

declare(strict_types=1);

namespace App\Tests\Unit\Households\Domain\ValueObject;

use App\Households\Domain\ValueObject\HouseholdId;
use App\Households\Domain\ValueObject\HouseholdLink;
use App\Households\Domain\ValueObject\MemberHouseholdLinks;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[Small]
final class MemberHouseholdLinksTest extends TestCase
{
    private const string HOUSEHOLD_A = '019571bf-5d51-7000-b500-000000000005';
    private const string HOUSEHOLD_B = '019571bf-5d51-7000-b500-000000000006';

    #[Test]
    #[TestDox('::of() de-duplicates by household id, first occurrence wins, and exposes count()/householdIds().')]
    public function of_deduplicates_by_household_id_first_wins(): void
    {
        $householdA = HouseholdId::fromString(self::HOUSEHOLD_A);
        $first = new DateTimeImmutable('2026-01-01 12:00:00');
        $second = new DateTimeImmutable('2026-02-01 12:00:00');

        $links = MemberHouseholdLinks::of(
            HouseholdLink::of($householdA, $first),
            HouseholdLink::of($householdA, $second),
        );

        self::assertSame(1, $links->count());
        self::assertEquals($first, $links->linkedAt($householdA));
    }

    #[Test]
    #[TestDox('::none() produces an empty, valid collection.')]
    public function none_produces_an_empty_collection(): void
    {
        $links = MemberHouseholdLinks::none();

        self::assertSame(0, $links->count());
        self::assertSame([], $links->householdIds());
    }

    #[Test]
    #[TestDox('::includes() reports membership by household id.')]
    public function includes_reports_membership(): void
    {
        $householdA = HouseholdId::fromString(self::HOUSEHOLD_A);
        $links = MemberHouseholdLinks::of(HouseholdLink::of($householdA, new DateTimeImmutable()));

        self::assertTrue($links->includes($householdA));
        self::assertFalse($links->includes(HouseholdId::fromString(self::HOUSEHOLD_B)));
    }

    #[Test]
    #[TestDox('::linkedAt() returns the timestamp for an included household id, null otherwise.')]
    public function linked_at_returns_timestamp_or_null(): void
    {
        $householdA = HouseholdId::fromString(self::HOUSEHOLD_A);
        $linkedAt = new DateTimeImmutable('2026-01-01 12:00:00');
        $links = MemberHouseholdLinks::of(HouseholdLink::of($householdA, $linkedAt));

        self::assertEquals($linkedAt, $links->linkedAt($householdA));
        self::assertNull($links->linkedAt(HouseholdId::fromString(self::HOUSEHOLD_B)));
    }

    #[Test]
    #[TestDox('::householdIds() projects every distinct household id in insertion order.')]
    public function household_ids_projects_every_distinct_id(): void
    {
        $householdA = HouseholdId::fromString(self::HOUSEHOLD_A);
        $householdB = HouseholdId::fromString(self::HOUSEHOLD_B);
        $links = MemberHouseholdLinks::of(
            HouseholdLink::of($householdA, new DateTimeImmutable('2026-01-01 12:00:00')),
            HouseholdLink::of($householdB, new DateTimeImmutable('2026-02-01 12:00:00')),
        );

        self::assertEquals([$householdA, $householdB], $links->householdIds());
    }

    #[Test]
    #[TestDox('::equals() compares collections by member set and linked-at timestamps, order-independent.')]
    public function equals_compares_by_member_set_order_independent(): void
    {
        $householdA = HouseholdId::fromString(self::HOUSEHOLD_A);
        $householdB = HouseholdId::fromString(self::HOUSEHOLD_B);
        $atA = new DateTimeImmutable('2026-01-01 12:00:00');
        $atB = new DateTimeImmutable('2026-02-01 12:00:00');

        $a = MemberHouseholdLinks::of(HouseholdLink::of($householdA, $atA), HouseholdLink::of($householdB, $atB));
        $b = MemberHouseholdLinks::of(HouseholdLink::of($householdB, $atB), HouseholdLink::of($householdA, $atA));

        self::assertTrue($a->equals($b));
    }

    #[Test]
    #[TestDox('::equals() is false when the member sets differ in size.')]
    public function equals_false_for_different_size(): void
    {
        $householdA = HouseholdId::fromString(self::HOUSEHOLD_A);
        $a = MemberHouseholdLinks::of(HouseholdLink::of($householdA, new DateTimeImmutable()));
        $b = MemberHouseholdLinks::none();

        self::assertFalse($a->equals($b));
    }

    #[Test]
    #[TestDox('::equals() is false when a shared household id carries a different linked-at timestamp.')]
    public function equals_false_for_different_linked_at(): void
    {
        $householdA = HouseholdId::fromString(self::HOUSEHOLD_A);
        $a = MemberHouseholdLinks::of(HouseholdLink::of($householdA, new DateTimeImmutable('2026-01-01 12:00:00')));
        $b = MemberHouseholdLinks::of(HouseholdLink::of($householdA, new DateTimeImmutable('2026-02-01 12:00:00')));

        self::assertFalse($a->equals($b));
    }
}
