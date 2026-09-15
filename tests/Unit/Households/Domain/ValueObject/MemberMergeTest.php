<?php

declare(strict_types=1);

namespace App\Tests\Unit\Households\Domain\ValueObject;

use App\Households\Domain\ValueObject\MemberId;
use App\Households\Domain\ValueObject\MemberMerge;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[Small]
final class MemberMergeTest extends TestCase
{
    private const string SURVIVOR_ID = '019571bf-5d51-7000-b500-000000000002';

    #[Test]
    #[TestDox('::equals() is true for the same survivor id and timestamp.')]
    public function equals_true_for_same_values(): void
    {
        $at = new DateTimeImmutable('2026-01-01 12:00:00');
        $a = new MemberMerge(MemberId::fromString(self::SURVIVOR_ID), $at);
        $b = new MemberMerge(MemberId::fromString(self::SURVIVOR_ID), $at);

        self::assertTrue($a->equals($b));
    }

    #[Test]
    #[TestDox('::equals() is false when the survivor id differs.')]
    public function equals_false_for_different_survivor(): void
    {
        $at = new DateTimeImmutable('2026-01-01 12:00:00');
        $a = new MemberMerge(MemberId::fromString(self::SURVIVOR_ID), $at);
        $b = new MemberMerge(MemberId::fromString('019571bf-5d51-7000-b500-000000000003'), $at);

        self::assertFalse($a->equals($b));
    }

    #[Test]
    #[TestDox('::equals() is false when the timestamp differs.')]
    public function equals_false_for_different_timestamp(): void
    {
        $a = new MemberMerge(MemberId::fromString(self::SURVIVOR_ID), new DateTimeImmutable('2026-01-01 12:00:00'));
        $b = new MemberMerge(MemberId::fromString(self::SURVIVOR_ID), new DateTimeImmutable('2026-01-02 12:00:00'));

        self::assertFalse($a->equals($b));
    }
}
