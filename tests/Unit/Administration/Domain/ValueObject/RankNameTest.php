<?php

declare(strict_types=1);

namespace App\Tests\Unit\Administration\Domain\ValueObject;

use App\Administration\Domain\Exception\InvalidRankName;
use App\Administration\Domain\ValueObject\RankName;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[Small]
final class RankNameTest extends TestCase
{
    #[Test]
    #[TestDox('of() trims surrounding whitespace and round-trips the value.')]
    public function of_trims_whitespace(): void
    {
        $name = RankName::of('  Director  ');

        self::assertSame('Director', $name->value);
        self::assertSame('Director', (string) $name);
    }

    #[Test]
    #[TestWith([''], 'empty string')]
    #[TestWith(['   '], 'whitespace only')]
    #[TestDox('of() rejects an empty (after trim) name with InvalidRankName: $_dataName.')]
    public function of_rejects_empty_names(string $value): void
    {
        $this->expectException(InvalidRankName::class);

        self::assertInstanceOf(RankName::class, RankName::of($value));
    }

    #[Test]
    #[TestDox('of() rejects a name longer than 45 characters with InvalidRankName.')]
    public function of_rejects_names_over_the_length_limit(): void
    {
        $this->expectException(InvalidRankName::class);

        self::assertInstanceOf(RankName::class, RankName::of(str_repeat('a', RankName::MAX_LENGTH + 1)));
    }

    #[Test]
    #[TestDox('of() accepts a name exactly at the 45-character limit.')]
    public function of_accepts_the_length_limit_boundary(): void
    {
        $value = str_repeat('a', RankName::MAX_LENGTH);

        self::assertSame($value, RankName::of($value)->value);
    }

    #[Test]
    #[TestWith(['*Manager'], 'asterisk directly before the name')]
    #[TestWith(['  *Manager'], 'asterisk survives leading-whitespace trim')]
    #[TestDox('of() rejects the legacy leading-asterisk sort hack with InvalidRankName: $_dataName.')]
    public function of_rejects_a_leading_asterisk(string $value): void
    {
        $this->expectException(InvalidRankName::class);

        self::assertInstanceOf(RankName::class, RankName::of($value));
    }

    #[Test]
    #[TestDox('equals() compares case-insensitively, matching the functional LOWER(name) unique index.')]
    public function equals_compares_case_insensitively(): void
    {
        $a = RankName::of('Director');
        $b = RankName::of('director');
        $c = RankName::of('Manager');

        self::assertTrue($a->equals($b));
        self::assertFalse($a->equals($c));
    }
}
