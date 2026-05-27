<?php

declare(strict_types=1);

namespace App\Tests\Unit\Inventory\Infrastructure\Acl;

use App\Catalog\Domain\ListingKind;
use App\Catalog\Domain\ValueObject\ListingId;
use App\Catalog\Integration\Event\LineSold;
use App\Inventory\Domain\InventoryItem;
use App\Inventory\Domain\ValueObject\CostPerUnit;
use App\Inventory\Domain\ValueObject\FacilityCode;
use App\Inventory\Domain\ValueObject\InventoryItemId;
use App\Inventory\Domain\ValueObject\PosColor;
use App\Inventory\Domain\ValueObject\Quantity;
use App\Inventory\Domain\ValueObject\ReorderThreshold;
use App\Inventory\Domain\ValueObject\StockBatchId;
use App\Inventory\Infrastructure\Acl\CatalogIntegrationListener;
use App\Inventory\Infrastructure\Persistence\InMemory\InMemoryCombos;
use App\Inventory\Infrastructure\Persistence\InMemory\InMemoryInventoryItems;
use App\Inventory\Infrastructure\Persistence\InMemory\InMemoryItemLinks;
use App\Tests\Support\Fake\InMemoryStockMovementLedger;
use App\Tests\Support\Fake\RecordingMessageBus;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

/**
 * Pins the LRA-94 idempotency contract on the Catalog ACL: a duplicate
 * LineSold envelope (Messenger at-least-once redelivery) must not
 * re-consume stock and must not write a second CONSUMED ledger row.
 */
#[Small]
final class CatalogIntegrationListenerIdempotencyTest extends TestCase
{
    private const ITEM = '019571bf-5d51-7000-b500-000000008001';
    private const LISTING = '019571bf-5d51-7000-b500-000000008002';
    private const BATCH = '019571bf-5d51-7000-b500-000000008003';
    private const TX = 'tx-019571bf-5d51-7000-b500-000000008004';

    private InMemoryInventoryItems $items;
    private InMemoryStockMovementLedger $ledger;
    private CatalogIntegrationListener $listener;
    private MockClock $clock;

    protected function setUp(): void
    {
        $this->items = new InMemoryInventoryItems();
        $this->ledger = new InMemoryStockMovementLedger();
        $this->clock = new MockClock(new DateTimeImmutable('2026-05-26 13:00:00'));
        $this->listener = new CatalogIntegrationListener(
            $this->items,
            new InMemoryCombos(),
            new InMemoryItemLinks(),
            $this->clock,
            new RecordingMessageBus(),
            $this->ledger,
        );

        $item = InventoryItem::register(
            InventoryItemId::fromString(self::ITEM),
            ListingId::fromString(self::LISTING),
            primaryVendorId: null,
            posColor: PosColor::default(),
            trackInventory: true,
            rentable: false,
            reorderThreshold: ReorderThreshold::ofUnits(0),
            clock: $this->clock,
        );
        $item->receiveBatch(
            FacilityCode::fromString('MAIN'),
            Quantity::ofUnits(10),
            CostPerUnit::ofCents(199),
            null,
            null,
            StockBatchId::fromString(self::BATCH),
            $this->clock,
        );
        $this->items->add($item);
    }

    #[Test]
    #[TestDox('Re-dispatching the same LineSold envelope consumes stock only once.')]
    public function double_dispatch_is_idempotent(): void
    {
        $event = new LineSold(
            listingId: self::LISTING,
            listingKind: ListingKind::Inventory->value,
            listingCode: 'TEST-CODE',
            quantity: 3,
            facilityCode: 'MAIN',
            transactionId: self::TX,
            occurredAt: $this->clock->now(),
        );

        ($this->listener)($event);
        ($this->listener)($event);

        $remaining = $this->items->byId(InventoryItemId::fromString(self::ITEM))
            ->totalOnHandAt(FacilityCode::fromString('MAIN'));

        self::assertSame(7, $remaining->units, 'Stock should be consumed exactly once for a duplicate envelope.');

        $consumeRows = array_values(array_filter(
            $this->ledger->rows(),
            static fn (array $row): bool => $row['kind'] === 'CONSUMED',
        ));
        self::assertCount(1, $consumeRows, 'Ledger should hold exactly one CONSUMED row for the duplicate envelope.');
        self::assertSame(self::TX, $consumeRows[0]['transaction_id']);
        self::assertSame(3, $consumeRows[0]['quantity']);
    }
}
