<?php

declare(strict_types=1);

namespace App\Tests\Unit\Administration\Domain\ValueObject;

use App\Administration\Domain\Exception\InvalidSeniorityLevel;
use App\Administration\Domain\ValueObject\SeniorityLevel;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

/**
 * The inverted ordering (ascending = less senior) is the single most
 * misreadable rule in LRA-267, so it gets the densest coverage in this
 * suite.
 */
#[Small]
final class SeniorityLevelTest extends TestCase
{
    /**
     * @return Generator<string, array{0: int, 1: int, 2: bool}>
     */
    public static function isAtLeastAsSeniorAsCases(): Generator
    {
        yield '0 (most senior) is at least as senior as 1' => [0, 1, true];
        yield '1 is not at least as senior as 0 (most senior)' => [1, 0, false];
        yield '20 is at least as senior as itself' => [20, 20, true];
        yield '20 is at least as senior as 100 (least senior)' => [20, 100, true];
        yield '100 (least senior) is not at least as senior as 0 (most senior)' => [100, 0, false];
        yield 'boundary: 0 is at least as senior as 100' => [0, 100, true];
        yield 'boundary: 100 is at least as senior as 100' => [100, 100, true];
    }

    /**
     * @param int $subject the seniority value under test
     * @param int $other the seniority value being compared against
     * @param bool $expected the expected isAtLeastAsSeniorAs() result
     */
    #[Test]
    #[DataProvider('isAtLeastAsSeniorAsCases')]
    #[TestDox('isAtLeastAsSeniorAs() treats ascending values as less senior: $_dataName.')]
    public function is_at_least_as_senior_as_honours_inverted_ordering(int $subject, int $other, bool $expected): void
    {
        self::assertSame(
            $expected,
            SeniorityLevel::of($subject)->isAtLeastAsSeniorAs(SeniorityLevel::of($other)),
        );
    }

    /**
     * @return Generator<string, array{0: int, 1: int, 2: bool}>
     */
    public static function isMoreSeniorThanCases(): Generator
    {
        yield '0 (most senior) is strictly more senior than 1' => [0, 1, true];
        yield '1 is not strictly more senior than 0 (most senior)' => [1, 0, false];
        yield '20 is not strictly more senior than itself' => [20, 20, false];
        yield '20 is strictly more senior than 100 (least senior)' => [20, 100, true];
    }

    /**
     * @param int $subject the seniority value under test
     * @param int $other the seniority value being compared against
     * @param bool $expected the expected isMoreSeniorThan() result
     */
    #[Test]
    #[DataProvider('isMoreSeniorThanCases')]
    #[TestDox('isMoreSeniorThan() is strict and honours inverted ordering: $_dataName.')]
    public function is_more_senior_than_honours_inverted_ordering(int $subject, int $other, bool $expected): void
    {
        self::assertSame(
            $expected,
            SeniorityLevel::of($subject)->isMoreSeniorThan(SeniorityLevel::of($other)),
        );
    }

    #[Test]
    #[TestDox('equals() compares by value.')]
    public function equals_compares_by_value(): void
    {
        self::assertTrue(SeniorityLevel::of(22)->equals(SeniorityLevel::of(22)));
        self::assertFalse(SeniorityLevel::of(22)->equals(SeniorityLevel::of(21)));
    }

    #[Test]
    #[TestWith([0])]
    #[TestWith([100])]
    #[TestWith([50])]
    #[TestDox('of() accepts every value within the 0-100 inclusive range: $value.')]
    public function of_accepts_the_inclusive_range(int $value): void
    {
        self::assertSame($value, SeniorityLevel::of($value)->value);
    }

    #[Test]
    #[TestWith([-1], 'below minimum')]
    #[TestWith([101], 'above maximum')]
    #[TestDox('of() rejects a value outside 0-100 with InvalidSeniorityLevel: $_dataName.')]
    public function of_rejects_out_of_range_values(int $value): void
    {
        $this->expectException(InvalidSeniorityLevel::class);

        self::assertInstanceOf(SeniorityLevel::class, SeniorityLevel::of($value));
    }
}
