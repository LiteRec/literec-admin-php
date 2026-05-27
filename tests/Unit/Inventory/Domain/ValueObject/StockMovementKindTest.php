<?php

declare(strict_types=1);

namespace App\Tests\Unit\Inventory\Domain\ValueObject;

use App\Inventory\Domain\ValueObject\StockMovementKind;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[Small]
final class StockMovementKindTest extends TestCase
{
    /**
     * @return \Generator<string, array{kind: StockMovementKind, expected: string}>
     */
    public static function labelCases(): \Generator
    {
        yield 'RECEIVED'          => ['kind' => StockMovementKind::RECEIVED, 'expected' => 'Received'];
        yield 'CONSUMED'          => ['kind' => StockMovementKind::CONSUMED, 'expected' => 'Consumed'];
        yield 'RETURNED'          => ['kind' => StockMovementKind::RETURNED, 'expected' => 'Returned'];
        yield 'TRANSFERRED_OUT'   => ['kind' => StockMovementKind::TRANSFERRED_OUT, 'expected' => 'Transferred out'];
        yield 'TRANSFERRED_IN'    => ['kind' => StockMovementKind::TRANSFERRED_IN, 'expected' => 'Transferred in'];
        yield 'ADJUSTED_INCREASE' => ['kind' => StockMovementKind::ADJUSTED_INCREASE, 'expected' => 'Adjusted +'];
        yield 'ADJUSTED_DECREASE' => ['kind' => StockMovementKind::ADJUSTED_DECREASE, 'expected' => 'Adjusted −'];
    }

    #[Test]
    #[TestDox('Each enum case maps to its operator-facing label.')]
    #[DataProvider('labelCases')]
    public function each_case_maps_to_its_label(StockMovementKind $kind, string $expected): void
    {
        self::assertSame($expected, $kind->label());
    }

    /**
     * @return \Generator<string, array{value: string, expected: StockMovementKind}>
     */
    public static function roundTripCases(): \Generator
    {
        yield 'RECEIVED'          => ['value' => 'RECEIVED', 'expected' => StockMovementKind::RECEIVED];
        yield 'CONSUMED'          => ['value' => 'CONSUMED', 'expected' => StockMovementKind::CONSUMED];
        yield 'RETURNED'          => ['value' => 'RETURNED', 'expected' => StockMovementKind::RETURNED];
        yield 'TRANSFERRED_OUT'   => ['value' => 'TRANSFERRED_OUT', 'expected' => StockMovementKind::TRANSFERRED_OUT];
        yield 'TRANSFERRED_IN'    => ['value' => 'TRANSFERRED_IN', 'expected' => StockMovementKind::TRANSFERRED_IN];
        yield 'ADJUSTED_INCREASE' => [
            'value' => 'ADJUSTED_INCREASE',
            'expected' => StockMovementKind::ADJUSTED_INCREASE,
        ];
        yield 'ADJUSTED_DECREASE' => [
            'value' => 'ADJUSTED_DECREASE',
            'expected' => StockMovementKind::ADJUSTED_DECREASE,
        ];
    }

    #[Test]
    #[TestDox('tryFrom() round-trips each enum value back to its case.')]
    #[DataProvider('roundTripCases')]
    public function try_from_round_trips_each_value(string $value, StockMovementKind $expected): void
    {
        self::assertSame($expected, StockMovementKind::tryFrom($value));
    }

    #[Test]
    #[TestDox('tryFrom() returns null for an unknown string value.')]
    public function try_from_returns_null_for_unknown(): void
    {
        // Build the string at runtime so PHPStan cannot narrow tryFrom
        // to a literal-null return based on the known enum value list.
        $unknown = strtolower(self::randomLabel()); // 'bogus' → not an enum value
        self::assertNull(StockMovementKind::tryFrom($unknown));
    }

    private static function randomLabel(): string
    {
        return 'BOGUS';
    }

    /**
     * @return \Generator<string, array{kind: StockMovementKind, outbound: bool}>
     */
    public static function outboundCases(): \Generator
    {
        yield 'CONSUMED is outbound'         => ['kind' => StockMovementKind::CONSUMED,         'outbound' => true];
        yield 'TRANSFERRED_OUT is outbound'  => ['kind' => StockMovementKind::TRANSFERRED_OUT,  'outbound' => true];
        yield 'ADJUSTED_DECREASE is outbound' => ['kind' => StockMovementKind::ADJUSTED_DECREASE, 'outbound' => true];
        yield 'RECEIVED is inbound'          => ['kind' => StockMovementKind::RECEIVED,         'outbound' => false];
        yield 'RETURNED is inbound'          => ['kind' => StockMovementKind::RETURNED,         'outbound' => false];
        yield 'TRANSFERRED_IN is inbound'    => ['kind' => StockMovementKind::TRANSFERRED_IN,   'outbound' => false];
        yield 'ADJUSTED_INCREASE is inbound' => ['kind' => StockMovementKind::ADJUSTED_INCREASE, 'outbound' => false];
    }

    #[Test]
    #[TestDox('isOutbound() reflects whether the kind decrements on-hand.')]
    #[DataProvider('outboundCases')]
    public function is_outbound_reflects_direction(StockMovementKind $kind, bool $outbound): void
    {
        self::assertSame($outbound, $kind->isOutbound());
    }
}
