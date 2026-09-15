<?php

declare(strict_types=1);

namespace App\Tests\Unit\Households\Domain\ValueObject;

use App\Households\Domain\ValueObject\TransactionReference;
use App\Households\Domain\ValueObject\TransactionReferences;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[Small]
final class TransactionReferencesTest extends TestCase
{
    #[Test]
    #[TestDox('::of() de-duplicates equal references and exposes count()/contains().')]
    public function of_deduplicates(): void
    {
        $refs = TransactionReferences::of(
            TransactionReference::of('txn-1'),
            TransactionReference::of('txn-2'),
            TransactionReference::of('txn-1'),
        );

        self::assertSame(2, $refs->count());
        self::assertTrue($refs->contains(TransactionReference::of('txn-1')));
        self::assertTrue($refs->contains(TransactionReference::of('txn-2')));
        self::assertFalse($refs->contains(TransactionReference::of('txn-3')));
    }

    #[Test]
    #[TestDox('::fromStrings() builds the same collection as ::of() from raw strings.')]
    public function from_strings_builds_collection(): void
    {
        $refs = TransactionReferences::fromStrings(['txn-1', 'txn-2', 'txn-1']);

        self::assertSame(2, $refs->count());
        self::assertSame(['txn-1', 'txn-2'], $refs->toStrings());
    }

    #[Test]
    #[TestDox('::fromStrings() with no elements produces an empty, valid collection.')]
    public function from_strings_allows_empty(): void
    {
        $refs = TransactionReferences::fromStrings([]);

        self::assertSame(0, $refs->count());
        self::assertSame([], $refs->toStrings());
    }

    #[Test]
    #[TestDox('::equals() compares collections by their de-duplicated member set, order-independent.')]
    public function equals_compares_by_member_set(): void
    {
        $a = TransactionReferences::fromStrings(['txn-1', 'txn-2']);
        $b = TransactionReferences::fromStrings(['txn-2', 'txn-1']);
        $c = TransactionReferences::fromStrings(['txn-1']);

        self::assertTrue($a->equals($b));
        self::assertFalse($a->equals($c));
    }
}
