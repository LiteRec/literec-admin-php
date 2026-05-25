<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Domain\ValueObject;

use App\Catalog\Domain\ValueObject\Currency;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[Small]
final class CurrencyTest extends TestCase
{
    #[Test]
    #[TestDox(
        'Has exactly one case (USD) — adding a second case must be paired with '
        . 're-enabling the currency comparison in Money::equals().'
    )]
    public function single_case_guard(): void
    {
        self::assertCount(
            1,
            Currency::cases(),
            'When Currency gains a second case, update Money::equals() to compare currencies and remove this guard.'
        );
        self::assertSame('USD', Currency::USD->value);
    }
}
