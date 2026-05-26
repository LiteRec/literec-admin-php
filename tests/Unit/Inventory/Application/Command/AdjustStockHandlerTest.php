<?php

declare(strict_types=1);

namespace App\Tests\Unit\Inventory\Application\Command;

use App\Inventory\Application\Command\AdjustStock;
use App\Inventory\Application\Command\AdjustStockHandler;
use App\Inventory\Domain\Event\StockMovementRecorded;
use App\Inventory\Domain\Event\StockReceived;
use App\Inventory\Domain\Exception\AdjustmentReasonRequired;
use App\Inventory\Domain\ValueObject\InventoryItemId;
use App\Inventory\Domain\ValueObject\StockBatchId;
use App\Inventory\Domain\ValueObject\StockMovementReason;
use App\Inventory\Infrastructure\Persistence\InMemory\InMemoryInventoryItems;
use App\Tests\Support\Fake\RecordingMessageBus;
use App\Tests\Support\Fake\SequenceInventoryIdentityGenerator;
use App\Tests\Support\Trait\SeedsInventoryItemWithBatch;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[Small]
final class AdjustStockHandlerTest extends TestCase
{
    use SeedsInventoryItemWithBatch;

    private const ITEM = '019571bf-5d51-7000-b500-000000001200';
    private const LISTING = '019571bf-5d51-7000-b500-000000001201';
    private const SEED_BATCH = '019571bf-5d51-7000-b500-000000001202';
    private const FOUND_BATCH = '019571bf-5d51-7000-b500-000000001203';

    private InMemoryInventoryItems $items;
    private RecordingMessageBus $eventBus;
    private MockClock $clock;
    private SequenceInventoryIdentityGenerator $ids;
    private AdjustStockHandler $handler;

    protected function setUp(): void
    {
        $this->items = new InMemoryInventoryItems();
        $this->eventBus = new RecordingMessageBus();
        $this->clock = new MockClock(new DateTimeImmutable('2026-05-26 10:00:00'));
        $this->ids = new SequenceInventoryIdentityGenerator(
            stockBatchIds: [StockBatchId::fromString(self::FOUND_BATCH)],
        );
        $this->handler = new AdjustStockHandler(
            $this->items,
            $this->ids,
            $this->clock,
            $this->eventBus,
        );

        $this->seedItemWithBatch(
            $this->items,
            $this->clock,
            self::ITEM,
            self::LISTING,
            'MAIN',
            self::SEED_BATCH,
            10,
            200,
        );
    }

    #[Test]
    #[TestDox('Empty reason throws AdjustmentReasonRequired before any state change.')]
    public function empty_reason_throws_before_mutation(): void
    {
        try {
            ($this->handler)(new AdjustStock(self::ITEM, 'MAIN', 7, '   '));
            self::fail('Expected AdjustmentReasonRequired.');
        } catch (AdjustmentReasonRequired) {
            // ok
        }

        $loaded = $this->items->byId(InventoryItemId::fromString(self::ITEM));
        self::assertSame(10, $loaded->totalOnHand()->units, 'no batches should have mutated');
        self::assertSame([], $this->eventBus->dispatchedMessages());
    }

    #[Test]
    #[TestDox('Positive variance books a zero-cost found-stock batch tagged with the operator reason.')]
    public function positive_variance_creates_found_stock_batch(): void
    {
        ($this->handler)(new AdjustStock(self::ITEM, 'MAIN', 15, 'Recount: found 5 in back room'));

        $loaded = $this->items->byId(InventoryItemId::fromString(self::ITEM));
        self::assertSame(15, $loaded->totalOnHand()->units);

        $messages = $this->eventBus->dispatchedMessages();
        self::assertCount(1, $messages);
        $event = $messages[0];
        self::assertInstanceOf(StockReceived::class, $event);
        self::assertSame(self::FOUND_BATCH, $event->stockBatchId->value);
        self::assertSame(5, $event->quantity->units);
        self::assertSame(0, $event->costPerUnit->cents);
        self::assertNotNull($event->comments);
        self::assertSame('Recount: found 5 in back room', $event->comments->value);
    }

    #[Test]
    #[TestDox('Negative variance consumes from FIFO batches with reason=ADJUSTMENT.')]
    public function negative_variance_consumes_with_adjustment_reason(): void
    {
        ($this->handler)(new AdjustStock(self::ITEM, 'MAIN', 6, 'Shrinkage'));

        $loaded = $this->items->byId(InventoryItemId::fromString(self::ITEM));
        self::assertSame(6, $loaded->totalOnHand()->units);

        $messages = $this->eventBus->dispatchedMessages();
        self::assertCount(1, $messages);
        $event = $messages[0];
        self::assertInstanceOf(StockMovementRecorded::class, $event);
        self::assertSame(StockMovementReason::ADJUSTMENT, $event->reason);
        self::assertSame(4, $event->quantityConsumed->units);
    }

    #[Test]
    #[TestDox('Zero variance is a no-op (no events, no mutation).')]
    public function zero_variance_is_noop(): void
    {
        ($this->handler)(new AdjustStock(self::ITEM, 'MAIN', 10, 'Counted, no change'));

        $loaded = $this->items->byId(InventoryItemId::fromString(self::ITEM));
        self::assertSame(10, $loaded->totalOnHand()->units);
        self::assertSame([], $this->eventBus->dispatchedMessages());
    }
}
