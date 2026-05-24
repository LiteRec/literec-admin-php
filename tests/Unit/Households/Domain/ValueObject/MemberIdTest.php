<?php

declare(strict_types=1);

namespace App\Tests\Unit\Households\Domain\ValueObject;

use App\Households\Domain\Exception\InvalidMemberId;
use App\Households\Domain\ValueObject\MemberId;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[Small]
final class MemberIdTest extends TestCase
{
    #[Test]
    #[TestDox('Accepts a valid canonical UUID v7 string.')]
    public function accepts_a_valid_uuid_v7(): void
    {
        $value = '019571bf-5d51-7000-b500-0123456789ab';

        self::assertSame($value, MemberId::fromString($value)->value);
    }

    #[Test]
    #[TestWith([''])]
    #[TestWith(['not-a-uuid'])]
    #[TestWith(['12345678-1234-4abc-9def-0123456789ab'])]
    #[TestDox('Rejects an invalid UUID v7 with InvalidMemberId.')]
    public function rejects_invalid_uuid_v7(string $value): void
    {
        $this->expectException(InvalidMemberId::class);

        MemberId::fromString($value);
    }

    #[Test]
    #[TestDox('Equals another MemberId with the same value.')]
    public function equals_an_identical_id(): void
    {
        $a = MemberId::fromString('019571bf-5d51-7000-b500-0123456789ab');
        $b = MemberId::fromString('019571bf-5d51-7000-b500-0123456789ab');

        self::assertTrue($a->equals($b));
    }

    #[Test]
    #[TestDox('Does not equal another MemberId with a different value.')]
    public function does_not_equal_a_different_id(): void
    {
        $a = MemberId::fromString('019571bf-5d51-7000-b500-0123456789ab');
        $b = MemberId::fromString('019571bf-5d51-7000-b500-fedcba987654');

        self::assertFalse($a->equals($b));
    }

    #[Test]
    #[TestDox('Stringifies to the stored value.')]
    public function stringifies_to_the_stored_value(): void
    {
        $value = '019571bf-5d51-7000-b500-0123456789ab';

        self::assertSame($value, (string) MemberId::fromString($value));
    }
}
