<?php

declare(strict_types=1);

namespace App\Tests\Support\Trait;

use App\Catalog\Domain\ValueObject\ListingId;
use App\Inventory\Domain\Exception\DuplicateInventoryItemForListing;
use App\Inventory\Domain\Exception\InventoryItemNotFound;
use App\Inventory\Domain\InventoryItem;
use App\Inventory\Domain\InventoryItems;
use App\Inventory\Domain\ValueObject\CostPerUnit;
use App\Inventory\Domain\ValueObject\FacilityCode;
use App\Inventory\Domain\ValueObject\InventoryItemId;
use App\Inventory\Domain\ValueObject\PosColor;
use App\Inventory\Domain\ValueObject\Quantity;
use App\Inventory\Domain\ValueObject\ReorderThreshold;
use App\Inventory\Domain\ValueObject\StockBatchId;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Component\Clock\MockClock;

/**
 * Shared behavioural contract for any {@see InventoryItems} adapter.
 *
 * Concrete test classes ({@see InMemoryInventoryItemsContractTest},
 * {@see DoctrineInventoryItemsContractTest}) use this trait so the two
 * implementations cannot drift apart. Read-side queries (search,
 * low-stock, movement history) live on a sibling read-model port
 * introduced by LRA-84 — this contract covers only the write-side and
 * identity-based finders.
 */
trait InventoryItemsContractCases
{
    private const ITEM_A = '019571bf-5d51-7000-b500-000000000700';
    private const ITEM_B = '019571bf-5d51-7000-b500-000000000701';
    private const LISTING_A = '019571bf-5d51-7000-b500-000000000710';
    private const LISTING_B = '019571bf-5d51-7000-b500-000000000711';
    private const BATCH_1 = '019571bf-5d51-7000-b500-000000000801';
    private const BATCH_2 = '019571bf-5d51-7000-b500-000000000802';

    abstract protected function inventoryItems(): InventoryItems;

    abstract protected function clock(): MockClock;

    #[Test]
    #[TestDox('add() then byId() round-trips the aggregate including batches in FIFO order.')]
    public function add_then_by_id_round_trips(): void
    {
        $item = $this->makeItem(self::ITEM_A, self::LISTING_A);
        $facility = FacilityCode::fromString('MAIN');

        $item->receiveBatch(
            $facility,
            Quantity::ofUnits(10),
            CostPerUnit::ofCents(250),
            null,
            null,
            StockBatchId::fromString(self::BATCH_1),
            $this->clock(),
        );
        $this->clock()->modify('+1 minute');
        $item->receiveBatch(
            $facility,
            Quantity::ofUnits(5),
            CostPerUnit::ofCents(300),
            null,
            null,
            StockBatchId::fromString(self::BATCH_2),
            $this->clock(),
        );

        $this->inventoryItems()->add($item);

        $loaded = $this->inventoryItems()->byId(InventoryItemId::fromString(self::ITEM_A));

        self::assertTrue($loaded->id()->equals($item->id()));
        self::assertTrue($loaded->listingId()->equals(ListingId::fromString(self::LISTING_A)));
        self::assertSame(15, $loaded->totalOnHandAt($facility)->units);

        $batches = $loaded->batches();
        self::assertCount(2, $batches);
        self::assertSame(self::BATCH_1, $batches[0]->id()->value);
        self::assertSame(self::BATCH_2, $batches[1]->id()->value);
    }

    #[Test]
    #[TestDox('byListingId() returns the registered item for a given catalog listing.')]
    public function by_listing_id_returns_item(): void
    {
        $item = $this->makeItem(self::ITEM_A, self::LISTING_A);
        $this->inventoryItems()->add($item);

        $loaded = $this->inventoryItems()->byListingId(ListingId::fromString(self::LISTING_A));

        self::assertTrue($loaded->id()->equals(InventoryItemId::fromString(self::ITEM_A)));
    }

    #[Test]
    #[TestDox('byListingId() throws InventoryItemNotFound when no item is registered for the listing.')]
    public function by_listing_id_missing_throws(): void
    {
        $this->expectException(InventoryItemNotFound::class);

        $this->inventoryItems()->byListingId(ListingId::fromString(self::LISTING_A));
    }

    #[Test]
    #[TestDox('byId() throws InventoryItemNotFound when no item with the given id exists.')]
    public function by_id_missing_throws(): void
    {
        $this->expectException(InventoryItemNotFound::class);

        $this->inventoryItems()->byId(InventoryItemId::fromString(self::ITEM_A));
    }

    #[Test]
    #[TestDox('existsForListing() reflects the one-inventory-item-per-listing invariant.')]
    public function exists_for_listing(): void
    {
        $listing = ListingId::fromString(self::LISTING_A);

        self::assertFalse($this->inventoryItems()->existsForListing($listing));

        $this->inventoryItems()->add($this->makeItem(self::ITEM_A, self::LISTING_A));

        self::assertTrue($this->inventoryItems()->existsForListing($listing));
        self::assertFalse(
            $this->inventoryItems()->existsForListing(ListingId::fromString(self::LISTING_B)),
        );
    }

    #[Test]
    #[TestDox('add() throws DuplicateInventoryItemForListing when a second item targets the same listing.')]
    public function add_duplicate_listing_throws(): void
    {
        $this->inventoryItems()->add($this->makeItem(self::ITEM_A, self::LISTING_A));

        $this->expectException(DuplicateInventoryItemForListing::class);

        $this->inventoryItems()->add($this->makeItem(self::ITEM_B, self::LISTING_A));
    }

    #[Test]
    #[TestDox('save() persists subsequent updates to an existing aggregate.')]
    public function save_persists_updates(): void
    {
        $item = $this->makeItem(self::ITEM_A, self::LISTING_A);
        $this->inventoryItems()->add($item);

        $item->updatePosColor(PosColor::ofHex('#112233'), $this->clock());
        $this->inventoryItems()->save($item);

        $loaded = $this->inventoryItems()->byId(InventoryItemId::fromString(self::ITEM_A));
        self::assertSame('#112233', $loaded->posColor()->hex);
    }

    #[Test]
    #[TestDox('save() throws InventoryItemNotFound for an aggregate that was never persisted.')]
    public function save_unknown_throws(): void
    {
        $orphan = $this->makeItem(self::ITEM_A, self::LISTING_A);

        $this->expectException(InventoryItemNotFound::class);

        $this->inventoryItems()->save($orphan);
    }

    private function makeItem(string $itemId, string $listingId): InventoryItem
    {
        return InventoryItem::register(
            InventoryItemId::fromString($itemId),
            ListingId::fromString($listingId),
            null,
            PosColor::default(),
            true,
            false,
            ReorderThreshold::none(),
            $this->clock(),
        );
    }
}
