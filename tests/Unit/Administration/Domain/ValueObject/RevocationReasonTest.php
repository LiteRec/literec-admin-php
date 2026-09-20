<?php

declare(strict_types=1);

namespace App\Tests\Unit\Administration\Domain\ValueObject;

use App\Administration\Domain\Exception\InvalidRevocationReason;
use App\Administration\Domain\ValueObject\RevocationReason;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[Small]
final class RevocationReasonTest extends TestCase
{
    #[Test]
    #[TestDox('of() trims surrounding whitespace and preserves the text.')]
    public function of_trims_surrounding_whitespace(): void
    {
        $reason = RevocationReason::of('  Left the organization.  ');

        self::assertSame('Left the organization.', $reason->value);
    }

    #[Test]
    #[TestDox('of() rejects an empty (or whitespace-only) reason with InvalidRevocationReason.')]
    public function of_rejects_empty_reason(): void
    {
        $this->expectException(InvalidRevocationReason::class);

        RevocationReason::of('   ');
    }

    #[Test]
    #[TestDox('of() rejects a reason longer than MAX_LENGTH with InvalidRevocationReason.')]
    public function of_rejects_reason_too_long(): void
    {
        $this->expectException(InvalidRevocationReason::class);

        RevocationReason::of(str_repeat('a', RevocationReason::MAX_LENGTH + 1));
    }

    #[Test]
    #[TestDox('of() accepts a reason exactly at MAX_LENGTH.')]
    public function of_accepts_reason_at_max_length(): void
    {
        $reason = RevocationReason::of(str_repeat('a', RevocationReason::MAX_LENGTH));

        self::assertSame(RevocationReason::MAX_LENGTH, strlen($reason->value));
    }

    #[Test]
    #[TestDox('equals() compares by value.')]
    public function equals_compares_by_value(): void
    {
        $a = RevocationReason::of('Left the organization.');
        $b = RevocationReason::of('Left the organization.');
        $c = RevocationReason::of('Policy violation.');

        self::assertTrue($a->equals($b));
        self::assertFalse($a->equals($c));
    }
}
