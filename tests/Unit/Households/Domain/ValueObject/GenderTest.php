<?php

declare(strict_types=1);

namespace App\Tests\Unit\Households\Domain\ValueObject;

use App\Households\Domain\ValueObject\Gender;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use ValueError;

#[Small]
final class GenderTest extends TestCase
{
    #[Test]
    #[TestDox('Exposes the four supported codes.')]
    public function exposes_supported_codes(): void
    {
        self::assertSame('F', Gender::Female->value);
        self::assertSame('M', Gender::Male->value);
        self::assertSame('O', Gender::Other->value);
        self::assertSame('U', Gender::Unspecified->value);
    }

    #[Test]
    #[TestDox('::from() returns the matching case for a known code.')]
    public function from_returns_matching_case(): void
    {
        self::assertSame(Gender::Female, Gender::from('F'));
    }

    #[Test]
    #[TestDox('::from() throws ValueError for an unknown code.')]
    public function from_throws_for_unknown_code(): void
    {
        $this->expectException(ValueError::class);

        Gender::from('X');
    }
}
