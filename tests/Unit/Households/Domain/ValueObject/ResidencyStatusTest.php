<?php

declare(strict_types=1);

namespace App\Tests\Unit\Households\Domain\ValueObject;

use App\Households\Domain\ValueObject\ResidencyStatus;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use ValueError;

#[Small]
final class ResidencyStatusTest extends TestCase
{
    #[Test]
    #[TestDox('Exposes exactly the four supported residency status codes.')]
    public function exposes_supported_codes(): void
    {
        self::assertCount(4, ResidencyStatus::cases());

        self::assertSame('RESIDENT', ResidencyStatus::Resident->value);
        self::assertSame('NON_RESIDENT', ResidencyStatus::NonResident->value);
        self::assertSame('MEMBER', ResidencyStatus::Member->value);
        self::assertSame('STAFF', ResidencyStatus::Staff->value);
    }

    #[Test]
    #[TestDox('::from() throws ValueError for an unknown code.')]
    public function from_rejects_unknown(): void
    {
        $this->expectException(ValueError::class);

        ResidencyStatus::from('VISITOR');
    }
}
