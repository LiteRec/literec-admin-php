<?php

declare(strict_types=1);

namespace App\Tests\Unit\Inventory\Domain\ValueObject;

use App\Inventory\Domain\Exception\InvalidFacilityCode;
use App\Inventory\Domain\ValueObject\FacilityCode;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[Small]
final class FacilityCodeTest extends TestCase
{
    /**
     * @return Generator<string, array{string}>
     */
    public static function validCases(): Generator
    {
        yield 'two uppercase letters'  => ['HQ'];
        yield 'with digits'            => ['HQ1'];
        yield 'with underscore'        => ['MAIN_POOL'];
        yield 'with hyphen'            => ['POOL-A'];
        yield 'sixteen char maximum'   => ['ABCDEFGHIJ123456'];
    }

    #[Test]
    #[DataProvider('validCases')]
    #[TestDox('Accepts a valid facility code: $_dataName.')]
    public function accepts_a_valid_facility_code(string $value): void
    {
        self::assertSame($value, FacilityCode::fromString($value)->value);
    }

    /**
     * @return Generator<string, array{string}>
     */
    public static function invalidCases(): Generator
    {
        yield 'empty'                => [''];
        yield 'single char too short' => ['A'];
        yield 'seventeen char too long' => ['ABCDEFGHIJ1234567'];
        yield 'lowercase rejected'   => ['hq'];
        yield 'starts with digit'    => ['1HQ'];
        yield 'starts with hyphen'   => ['-HQ'];
        yield 'starts with underscore' => ['_HQ'];
        yield 'contains space'       => ['HQ POOL'];
        yield 'contains punctuation' => ['HQ!'];
    }

    #[Test]
    #[DataProvider('invalidCases')]
    #[TestDox('Rejects an invalid facility code: $_dataName.')]
    public function rejects_invalid_facility_code(string $value): void
    {
        $this->expectException(InvalidFacilityCode::class);

        FacilityCode::fromString($value);
    }

    #[Test]
    #[TestDox('Stringifies to the canonical value.')]
    public function stringifies_to_the_canonical_value(): void
    {
        self::assertSame('HQ', (string) FacilityCode::fromString('HQ'));
    }

    #[Test]
    #[TestDox('equals() compares by string value.')]
    public function equals_compares_by_string_value(): void
    {
        $a = FacilityCode::fromString('HQ');
        $b = FacilityCode::fromString('HQ');
        $c = FacilityCode::fromString('POOL');

        self::assertTrue($a->equals($b));
        self::assertFalse($a->equals($c));
    }
}
