<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Domain;

use App\Catalog\Domain\ListingKind;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[Small]
final class ListingKindTest extends TestCase
{
    #[Test]
    #[TestDox('Exposes the five published kinds with stable string values used as integration contract.')]
    public function exposes_the_five_published_kinds(): void
    {
        self::assertSame('INVENTORY', ListingKind::Inventory->value);
        self::assertSame('PROGRAM', ListingKind::Program->value);
        self::assertSame('MEMBERSHIP', ListingKind::Membership->value);
        self::assertSame('RENTAL', ListingKind::Rental->value);
        self::assertSame('GIFT_CARD', ListingKind::GiftCard->value);
    }

    #[Test]
    #[TestDox('Has exactly five cases — adding one is an additive contract change handled by a separate ticket.')]
    public function has_exactly_five_cases(): void
    {
        self::assertCount(5, ListingKind::cases());
    }
}
