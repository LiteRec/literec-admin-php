<?php

declare(strict_types=1);

namespace App\Tests\Unit\Households\Integration\Event;

use App\Households\Integration\Event\MemberTransactionsSplitOff;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[Small]
final class MemberTransactionsSplitOffTest extends TestCase
{
    #[Test]
    #[TestDox('Constructs with wire-safe scalar fields.')]
    public function constructs_with_scalar_fields(): void
    {
        $occurredAt = new DateTimeImmutable('2026-05-24 12:00:00');

        $event = new MemberTransactionsSplitOff(
            householdId: 'h1',
            sourceMemberId: 'm1',
            newMemberId: 'm2',
            transactionIds: ['txn-1', 'txn-2'],
            occurredAt: $occurredAt,
        );

        self::assertSame('h1', $event->householdId);
        self::assertSame('m1', $event->sourceMemberId);
        self::assertSame('m2', $event->newMemberId);
        self::assertSame(['txn-1', 'txn-2'], $event->transactionIds);
        self::assertSame($occurredAt, $event->occurredAt);
    }

    /**
     * @param list<string> $transactionIds
     */
    #[Test]
    #[TestWith(['', 'm1', 'm2', ['txn-1']])]
    #[TestWith(['h1', '', 'm2', ['txn-1']])]
    #[TestWith(['h1', 'm1', '', ['txn-1']])]
    #[TestWith(['h1', 'm1', 'm2', []])]
    #[TestDox('Rejects an empty id or an empty transaction list.')]
    public function rejects_empty_fields(
        string $householdId,
        string $sourceMemberId,
        string $newMemberId,
        array $transactionIds,
    ): void {
        $this->expectException(InvalidArgumentException::class);

        new MemberTransactionsSplitOff(
            householdId: $householdId,
            sourceMemberId: $sourceMemberId,
            newMemberId: $newMemberId,
            transactionIds: $transactionIds,
            occurredAt: new DateTimeImmutable('2026-05-24 12:00:00'),
        );
    }
}
