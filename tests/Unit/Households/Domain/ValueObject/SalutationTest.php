<?php

declare(strict_types=1);

namespace App\Tests\Unit\Households\Domain\ValueObject;

use App\Households\Domain\ValueObject\Salutation;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use ValueError;

#[Small]
final class SalutationTest extends TestCase
{
    #[Test]
    #[TestDox('Exposes the six supported codes.')]
    public function exposes_supported_codes(): void
    {
        self::assertSame('MR', Salutation::Mr->value);
        self::assertSame('MRS', Salutation::Mrs->value);
        self::assertSame('MS', Salutation::Ms->value);
        self::assertSame('MX', Salutation::Mx->value);
        self::assertSame('DR', Salutation::Dr->value);
        self::assertSame('REV', Salutation::Rev->value);
    }

    #[Test]
    #[TestDox('::from() returns the matching case for a known code.')]
    public function from_returns_matching_case(): void
    {
        self::assertSame(Salutation::Dr, Salutation::from('DR'));
    }

    #[Test]
    #[TestDox('::from() throws ValueError for an unknown code.')]
    public function from_throws_for_unknown_code(): void
    {
        $this->expectException(ValueError::class);

        Salutation::from('X');
    }
}
