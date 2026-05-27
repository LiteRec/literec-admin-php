<?php

declare(strict_types=1);

namespace App\Tests\Unit\Inventory\Application\Command;

use App\Catalog\Application\Command\RegisterListing;
use App\Catalog\Domain\ValueObject\ListingId;
use App\Inventory\Application\Command\RegisterInventoryItem;
use App\Inventory\Application\Command\RegisterInventoryItemHandler;
use App\Inventory\Application\Exception\CrossBusRegistrationFailed;
use App\Inventory\Domain\Event\InventoryItemRegistered;
use App\Inventory\Domain\Exception\InventoryItemNotFound;
use App\Inventory\Domain\ValueObject\InventoryItemId;
use App\Inventory\Infrastructure\Persistence\InMemory\InMemoryInventoryItems;
use App\Tests\Support\Fake\NoStampCatalogCommandBus;
use App\Tests\Support\Fake\RecordingMessageBus;
use App\Tests\Support\Fake\SequenceInventoryIdentityGenerator;
use App\Tests\Support\Fake\StubCatalogCommandBus;
use App\Tests\Support\Fake\ThrowingCatalogCommandBus;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Clock\MockClock;

/**
 * Unit-level coverage of the LRA-98 cross-bus orchestration. The
 * Catalog dispatch is faked with a {@see StubCatalogCommandBus} that
 * returns a known {@see ListingId} via a HandledStamp, mirroring
 * what the real Catalog\RegisterListingHandler would produce.
 */
#[Small]
final class RegisterInventoryItemHandlerTest extends TestCase
{
    private const LISTING = '019571bf-5d51-7000-b500-00000000bb01';
    private const ITEM = '019571bf-5d51-7000-b500-00000000bb02';

    private InMemoryInventoryItems $items;
    private RecordingMessageBus $eventBus;
    private MockClock $clock;
    private SequenceInventoryIdentityGenerator $ids;

    protected function setUp(): void
    {
        $this->items = new InMemoryInventoryItems();
        $this->eventBus = new RecordingMessageBus();
        $this->clock = new MockClock(new DateTimeImmutable('2026-05-27 14:00:00'));
        $this->ids = new SequenceInventoryIdentityGenerator(
            inventoryItemIds: [InventoryItemId::fromString(self::ITEM)],
        );
    }

    #[Test]
    #[TestDox('Happy path: dispatches Catalog RegisterListing, persists InventoryItem, returns item id.')]
    public function happy_path_persists_inventory_item(): void
    {
        $handler = new RegisterInventoryItemHandler(
            commandBus: new StubCatalogCommandBus(ListingId::fromString(self::LISTING)),
            inventoryItems: $this->items,
            identityGenerator: $this->ids,
            clock: $this->clock,
            eventBus: $this->eventBus,
        );

        $resultItemId = ($handler)($this->makeCommand());

        self::assertSame(self::ITEM, $resultItemId);
        $loaded = $this->items->byId(InventoryItemId::fromString(self::ITEM));
        self::assertSame(self::LISTING, $loaded->listingId()->value);

        $events = array_values(array_filter(
            $this->eventBus->dispatchedMessages(),
            static fn (object $m): bool => $m instanceof InventoryItemRegistered,
        ));
        self::assertCount(1, $events, 'one InventoryItemRegistered event dispatched post-commit');
    }

    #[Test]
    #[TestDox('Catalog dispatch failure surfaces as CrossBusRegistrationFailed wrapping the cause.')]
    public function catalog_failure_wraps_in_named_exception(): void
    {
        $cause = new RuntimeException('Listing code already in use');
        $handler = new RegisterInventoryItemHandler(
            commandBus: new ThrowingCatalogCommandBus($cause),
            inventoryItems: $this->items,
            identityGenerator: $this->ids,
            clock: $this->clock,
            eventBus: $this->eventBus,
        );

        try {
            ($handler)($this->makeCommand());
            self::fail('Expected CrossBusRegistrationFailed.');
        } catch (CrossBusRegistrationFailed $thrown) {
            self::assertSame($cause, $thrown->getPrevious());
        }

        try {
            $this->items->byId(InventoryItemId::fromString(self::ITEM));
            self::fail('Expected InventoryItemNotFound — no item should have been persisted.');
        } catch (InventoryItemNotFound) {
            // expected: Catalog failed BEFORE the Inventory write happened
        }
        self::assertSame([], $this->eventBus->dispatchedMessages(), 'no events on failure path');
    }

    #[Test]
    #[TestDox('Missing HandledStamp on Catalog envelope surfaces as CrossBusRegistrationFailed.')]
    public function missing_handled_stamp_fails_loud(): void
    {
        $handler = new RegisterInventoryItemHandler(
            commandBus: new NoStampCatalogCommandBus(),
            inventoryItems: $this->items,
            identityGenerator: $this->ids,
            clock: $this->clock,
            eventBus: $this->eventBus,
        );

        $this->expectException(CrossBusRegistrationFailed::class);
        ($handler)($this->makeCommand());
    }

    private function makeCommand(): RegisterInventoryItem
    {
        return new RegisterInventoryItem(
            code: 'WIDGET-01',
            name: 'Test Widget',
            kind: 'INVENTORY',
            fees: [],
            taxApply: false,
            taxIncludedInFee: false,
            ledgerAccount: '4000.MERCH',
            primaryVendorId: null,
            posColorHex: '#1188FF',
            trackInventory: true,
            rentable: false,
            reorderThresholdUnits: 5,
        );
    }
}
