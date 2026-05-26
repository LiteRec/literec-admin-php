<?php

declare(strict_types=1);

namespace App\Tests\Unit\Inventory\Application\Command;

use App\Catalog\Domain\ValueObject\ListingId;
use App\Inventory\Application\Command\TransferStockBetweenFacilities;
use App\Inventory\Application\Command\TransferStockBetweenFacilitiesHandler;
use App\Inventory\Domain\Event\StockTransferredIn;
use App\Inventory\Domain\Event\StockTransferredOut;
use App\Inventory\Domain\Exception\CannotTransferToSameFacility;
use App\Inventory\Domain\Exception\InsufficientStock;
use App\Inventory\Domain\Exception\InventoryItemNotFound;
use App\Inventory\Domain\Exception\QuantityWouldGoNegative;
use App\Inventory\Domain\InventoryItem;
use App\Inventory\Domain\ValueObject\CostPerUnit;
use App\Inventory\Domain\ValueObject\FacilityCode;
use App\Inventory\Domain\ValueObject\InventoryItemId;
use App\Inventory\Domain\ValueObject\PosColor;
use App\Inventory\Domain\ValueObject\Quantity;
use App\Inventory\Domain\ValueObject\ReorderThreshold;
use App\Inventory\Domain\ValueObject\StockBatchId;
use App\Inventory\Infrastructure\Persistence\InMemory\InMemoryInventoryItems;
use App\Tests\Support\Fake\RecordingMessageBus;
use App\Tests\Support\Fake\SequenceInventoryIdentityGenerator;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[Small]
final class TransferStockBetweenFacilitiesHandlerTest extends TestCase
{
    private const ITEM = '019571bf-5d51-7000-b500-000000001300';
    private const LISTING = '019571bf-5d51-7000-b500-000000001301';
    private const SEED_BATCH = '019571bf-5d51-7000-b500-000000001302';
    private const NEW_BATCH = '019571bf-5d51-7000-b500-000000001303';

    private InMemoryInventoryItems $items;
    private RecordingMessageBus $eventBus;
    private MockClock $clock;
    private SequenceInventoryIdentityGenerator $ids;
    private TransferStockBetweenFacilitiesHandler $handler;

    protected function setUp(): void
    {
        $this->items = new InMemoryInventoryItems();
        $this->eventBus = new RecordingMessageBus();
        $this->clock = new MockClock(new DateTimeImmutable('2026-05-26 11:00:00'));
        $this->ids = new SequenceInventoryIdentityGenerator(
            stockBatchIds: [StockBatchId::fromString(self::NEW_BATCH)],
        );
        $this->handler = new TransferStockBetweenFacilitiesHandler(
            $this->items,
            $this->ids,
            $this->clock,
            $this->eventBus,
        );

        $item = InventoryItem::register(
            InventoryItemId::fromString(self::ITEM),
            ListingId::fromString(self::LISTING),
            null,
            PosColor::default(),
            true,
            false,
            ReorderThreshold::none(),
            $this->clock,
        );
        $item->receiveBatch(
            FacilityCode::fromString('MAIN'),
            Quantity::ofUnits(10),
            CostPerUnit::ofCents(400),
            null,
            null,
            StockBatchId::fromString(self::SEED_BATCH),
            $this->clock,
        );
        $item->releaseEvents();
        $this->items->add($item);
    }

    #[Test]
    #[TestDox('Moves units between facilities and emits StockTransferredOut + StockTransferredIn.')]
    public function happy_path(): void
    {
        ($this->handler)(new TransferStockBetweenFacilities(self::ITEM, 'MAIN', 'LAKESIDE', 4));

        $loaded = $this->items->byId(InventoryItemId::fromString(self::ITEM));
        self::assertSame(6, $loaded->totalOnHandAt(FacilityCode::fromString('MAIN'))->units);
        self::assertSame(4, $loaded->totalOnHandAt(FacilityCode::fromString('LAKESIDE'))->units);

        $messages = $this->eventBus->dispatchedMessages();
        // Expect: 1 SMR(OUT), 1 SMR(IN), 1 StockTransferredOut, 1 StockTransferredIn.
        self::assertCount(4, $messages);
        $txOut = $messages[2];
        $txIn = $messages[3];
        self::assertInstanceOf(StockTransferredOut::class, $txOut);
        self::assertInstanceOf(StockTransferredIn::class, $txIn);
        self::assertSame('MAIN', $txOut->fromFacility->value);
        self::assertSame('LAKESIDE', $txOut->toFacility->value);
        self::assertCount(1, $txIn->lineItems);
        self::assertSame(self::NEW_BATCH, $txIn->lineItems[0]->stockBatchId->value);
        self::assertSame(400, $txIn->lineItems[0]->costPerUnit->cents, 'cost basis preserved');
    }

    #[Test]
    #[TestDox('From == To throws CannotTransferToSameFacility.')]
    public function same_facility_throws(): void
    {
        $this->expectException(CannotTransferToSameFacility::class);

        ($this->handler)(new TransferStockBetweenFacilities(self::ITEM, 'MAIN', 'MAIN', 1));
    }

    #[Test]
    #[TestDox('Requesting more units than the source holds throws InsufficientStock; no events.')]
    public function insufficient_source_throws(): void
    {
        try {
            ($this->handler)(new TransferStockBetweenFacilities(self::ITEM, 'MAIN', 'LAKESIDE', 50));
            self::fail('Expected InsufficientStock.');
        } catch (InsufficientStock $e) {
            self::assertSame(50, $e->requested->units);
            self::assertSame(10, $e->available->units);
        }

        $loaded = $this->items->byId(InventoryItemId::fromString(self::ITEM));
        self::assertSame(10, $loaded->totalOnHandAt(FacilityCode::fromString('MAIN'))->units);
        self::assertSame(0, $loaded->totalOnHandAt(FacilityCode::fromString('LAKESIDE'))->units);
        self::assertSame([], $this->eventBus->dispatchedMessages());
    }

    #[Test]
    #[TestDox('Transferring zero units is a no-op (no events, no batch mutation).')]
    public function zero_quantity_is_noop(): void
    {
        ($this->handler)(new TransferStockBetweenFacilities(self::ITEM, 'MAIN', 'LAKESIDE', 0));

        $loaded = $this->items->byId(InventoryItemId::fromString(self::ITEM));
        self::assertSame(10, $loaded->totalOnHandAt(FacilityCode::fromString('MAIN'))->units);
        self::assertSame([], $this->eventBus->dispatchedMessages());
    }

    #[Test]
    #[TestDox('Unknown item id throws InventoryItemNotFound.')]
    public function unknown_item_throws(): void
    {
        $this->expectException(InventoryItemNotFound::class);

        ($this->handler)(new TransferStockBetweenFacilities(
            '019571bf-5d51-7000-b500-0000000013ff',
            'MAIN',
            'LAKESIDE',
            1,
        ));
    }

    #[Test]
    #[TestDox('Negative quantity is rejected by the Quantity VO before reaching the aggregate.')]
    public function negative_quantity_rejected_by_vo(): void
    {
        $this->expectException(QuantityWouldGoNegative::class);

        ($this->handler)(new TransferStockBetweenFacilities(self::ITEM, 'MAIN', 'LAKESIDE', -1));
    }
}
