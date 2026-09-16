<?php

declare(strict_types=1);

namespace App\Tests\Unit\Households\Domain\ValueObject;

use App\Households\Domain\ValueObject\Deactivation;
use App\Households\Domain\ValueObject\MemberId;
use App\Households\Domain\ValueObject\MemberLifecycle;
use App\Households\Domain\ValueObject\MemberMerge;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[Small]
final class MemberLifecycleTest extends TestCase
{
    private const string SURVIVOR_ID = '019571bf-5d51-7000-b500-000000000002';

    #[Test]
    #[TestDox('An active, never-anonymized, never-merged member reports isAnonymized()/isMerged() false.')]
    public function active_member_reports_not_anonymized_and_not_merged(): void
    {
        $lifecycle = new MemberLifecycle(true, null, null, null);

        self::assertTrue($lifecycle->isActive);
        self::assertFalse($lifecycle->isAnonymized());
        self::assertFalse($lifecycle->isMerged());
    }

    #[Test]
    #[TestDox('::isAnonymized() is true exactly when anonymizedAt is set.')]
    public function is_anonymized_reflects_anonymized_at(): void
    {
        $lifecycle = new MemberLifecycle(false, null, new DateTimeImmutable('2026-01-01 12:00:00'), null);

        self::assertTrue($lifecycle->isAnonymized());
    }

    #[Test]
    #[TestDox('::isMerged() is true exactly when a merge record is set.')]
    public function is_merged_reflects_merge_record(): void
    {
        $merge = new MemberMerge(MemberId::fromString(self::SURVIVOR_ID), new DateTimeImmutable('2026-01-01 12:00:00'));
        $lifecycle = new MemberLifecycle(false, null, null, $merge);

        self::assertTrue($lifecycle->isMerged());
    }

    #[Test]
    #[TestDox('::equals() is true when every one of the four facts matches.')]
    public function equals_true_for_same_values(): void
    {
        $deactivation = new Deactivation('moved away', new DateTimeImmutable('2026-01-01 12:00:00'));
        $anonymizedAt = new DateTimeImmutable('2026-02-01 12:00:00');
        $merge = new MemberMerge(MemberId::fromString(self::SURVIVOR_ID), new DateTimeImmutable('2026-03-01 12:00:00'));

        $a = new MemberLifecycle(false, $deactivation, $anonymizedAt, $merge);
        $b = new MemberLifecycle(false, $deactivation, $anonymizedAt, $merge);

        self::assertTrue($a->equals($b));
    }

    #[Test]
    #[TestDox('::equals() is false when isActive differs.')]
    public function equals_false_for_different_is_active(): void
    {
        $a = new MemberLifecycle(true, null, null, null);
        $b = new MemberLifecycle(false, null, null, null);

        self::assertFalse($a->equals($b));
    }

    #[Test]
    #[TestDox('::equals() null-safely compares the deactivation record.')]
    public function equals_null_safely_compares_deactivation(): void
    {
        $deactivation = new Deactivation('moved away', new DateTimeImmutable('2026-01-01 12:00:00'));
        $withDeactivation = new MemberLifecycle(false, $deactivation, null, null);
        $withoutDeactivation = new MemberLifecycle(true, null, null, null);

        self::assertFalse($withDeactivation->equals($withoutDeactivation));
        self::assertFalse($withoutDeactivation->equals($withDeactivation));
        self::assertTrue($withoutDeactivation->equals(new MemberLifecycle(true, null, null, null)));
    }

    #[Test]
    #[TestDox('::equals() is false when anonymizedAt differs.')]
    public function equals_false_for_different_anonymized_at(): void
    {
        $a = new MemberLifecycle(false, null, new DateTimeImmutable('2026-01-01 12:00:00'), null);
        $b = new MemberLifecycle(false, null, new DateTimeImmutable('2026-01-02 12:00:00'), null);

        self::assertFalse($a->equals($b));
    }

    #[Test]
    #[TestDox('::equals() null-safely compares the merge record.')]
    public function equals_null_safely_compares_merge(): void
    {
        $merge = new MemberMerge(MemberId::fromString(self::SURVIVOR_ID), new DateTimeImmutable('2026-01-01 12:00:00'));
        $merged = new MemberLifecycle(false, null, null, $merge);
        $unmerged = new MemberLifecycle(true, null, null, null);

        self::assertFalse($merged->equals($unmerged));
        self::assertFalse($unmerged->equals($merged));
    }
}
