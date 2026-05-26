<?php

declare(strict_types=1);

namespace App\Tests\Unit\Inventory\Domain\ValueObject;

use App\Inventory\Domain\Exception\InvalidPosColor;
use App\Inventory\Domain\ValueObject\PosColor;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[Small]
final class PosColorTest extends TestCase
{
    #[Test]
    #[TestDox('::ofHex() accepts a canonical uppercase #RRGGBB value.')]
    public function ofHex_accepts_uppercase(): void
    {
        $color = PosColor::ofHex('#A1B2C3');

        self::assertSame('#A1B2C3', $color->hex);
    }

    #[Test]
    #[TestDox('::ofHex() normalizes a lowercase value to uppercase.')]
    public function ofHex_normalizes_to_uppercase(): void
    {
        $color = PosColor::ofHex('#a1b2c3');

        self::assertSame('#A1B2C3', $color->hex);
    }

    #[Test]
    #[TestDox('::default() returns the #FFFFFF sentinel.')]
    public function default_returns_white(): void
    {
        self::assertSame('#FFFFFF', PosColor::default()->hex);
    }

    #[Test]
    #[TestDox('::equals() compares by canonical value.')]
    public function equals_compares_by_value(): void
    {
        self::assertTrue(PosColor::ofHex('#abcdef')->equals(PosColor::ofHex('#ABCDEF')));
        self::assertFalse(PosColor::ofHex('#000000')->equals(PosColor::ofHex('#FFFFFF')));
    }

    #[Test]
    #[TestWith(['#FFF'], 'three-character shorthand')]
    #[TestWith(['FFFFFF'], 'missing leading hash')]
    #[TestWith(['#GGGGGG'], 'non-hex characters')]
    #[TestWith(['#FFFFFFF'], 'eight-character value')]
    #[TestWith([''], 'empty string')]
    #[TestWith(['#'], 'hash only')]
    #[TestDox('::ofHex() rejects malformed input: $_dataName.')]
    public function ofHex_rejects_malformed(string $value): void
    {
        $this->expectException(InvalidPosColor::class);

        PosColor::ofHex($value);
    }
}
