<?php

declare(strict_types=1);

namespace App\Tests\Unit\Households\Domain\ValueObject;

use App\Households\Domain\Exception\InvalidTransactionReference;
use App\Households\Domain\ValueObject\TransactionReference;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

/**
 * TransactionReference is deliberately opaque (LRA-209): the Transactions
 * bounded context does not exist yet and its id format is undecided, so
 * this value object only rejects empty/whitespace strings and never
 * assumes a shape (UUID, integer, etc.).
 */
#[Small]
final class TransactionReferenceTest extends TestCase
{
    #[Test]
    #[TestDox('::of() trims and accepts any non-empty string, with no shape assumption.')]
    public function of_accepts_opaque_non_empty_strings(): void
    {
        self::assertSame('txn-1', TransactionReference::of('txn-1')->value);
        self::assertSame('12345', TransactionReference::of('  12345  ')->value);
        self::assertSame(
            '019571bf-5d51-7000-b500-000000000001',
            TransactionReference::of('019571bf-5d51-7000-b500-000000000001')->value,
        );
    }

    #[Test]
    #[TestWith([''])]
    #[TestWith(['   '])]
    #[TestDox('::of() throws InvalidTransactionReference on an empty or whitespace-only string.')]
    public function of_rejects_empty_or_whitespace(string $value): void
    {
        $this->expectException(InvalidTransactionReference::class);

        TransactionReference::of($value);
    }

    #[Test]
    #[TestDox('::equals() compares by value.')]
    public function equals_compares_by_value(): void
    {
        $a = TransactionReference::of('txn-1');
        $b = TransactionReference::of('txn-1');
        $c = TransactionReference::of('txn-2');

        self::assertTrue($a->equals($b));
        self::assertFalse($a->equals($c));
    }
}
