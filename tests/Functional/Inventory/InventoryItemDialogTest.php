<?php

declare(strict_types=1);

namespace App\Tests\Functional\Inventory;

use App\Catalog\Domain\Listing;
use App\Catalog\Domain\ListingKind;
use App\Catalog\Domain\Listings;
use App\Catalog\Domain\ValueObject\LedgerAccount;
use App\Catalog\Domain\ValueObject\ListingCode;
use App\Catalog\Domain\ValueObject\ListingId;
use App\Catalog\Domain\ValueObject\TaxTreatment;
use App\Inventory\Domain\InventoryItem;
use App\Inventory\Domain\InventoryItems;
use App\Inventory\Domain\ValueObject\CostPerUnit;
use App\Inventory\Domain\ValueObject\FacilityCode;
use App\Inventory\Domain\ValueObject\InventoryItemId;
use App\Inventory\Domain\ValueObject\PosColor;
use App\Inventory\Domain\ValueObject\Quantity;
use App\Inventory\Domain\ValueObject\ReorderThreshold;
use App\Inventory\Domain\ValueObject\StockBatchId;
use App\Tests\Support\Trait\SignsInUsers;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\MockClock;
use DateTimeImmutable;

/**
 * Drives the LRA-86 Create / Edit Inventory Item dialog and barcode
 * print page end-to-end. DAMA wraps each test in a transaction
 * rolled back at teardown so seeded rows and writes stay isolated.
 */
#[Large]
#[Group('database')]
final class InventoryItemDialogTest extends WebTestCase
{
    use SignsInUsers;

    private const string TEST_USERNAME = 'inventory_dialog_e2e';
    // NOSONAR — test fixture, not a real credential.
    private const string TEST_PASSWORD = 'CorrectHorseBattery!'; // NOSONAR

    private const string ITEM_ID = '019571bf-5d51-7000-b500-00000000df01';
    private const string LISTING_ID = '019571bf-5d51-7000-b500-00000000df02';
    private const string FACILITY = 'MAIN';
    private const string BATCH_ID = '019571bf-5d51-7000-b500-00000000df03';

    #[Test]
    #[TestDox('GET /admin/inventory/new returns a dialog fragment with the inventory-item form.')]
    public function get_new_form_returns_dialog_fragment(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);

        $client->request('GET', '/admin/inventory/new');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('div[role="dialog"][aria-modal="true"]');
        self::assertSelectorExists('input[name="inventory_item[code]"]');
        self::assertSelectorExists('input[name="inventory_item[_token]"]');
    }

    #[Test]
    #[TestDox('POST /admin/inventory/new with valid payload returns 200 + HX-Trigger and persists both rows.')]
    public function post_new_with_valid_payload_creates_listing_and_inventory_item(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);

        $beforeListings = $this->countRows('catalog_listings');
        $beforeItems = $this->countRows('inventory_items');

        $crawler = $client->request('GET', '/admin/inventory/new');
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Create Item')->form([
            'inventory_item[name]' => 'Test Widget',
            'inventory_item[code]' => 'TEST-WID-' . substr(bin2hex(random_bytes(3)), 0, 5),
            'inventory_item[kind]' => ListingKind::Inventory->value,
            'inventory_item[vendorId]' => '',
            'inventory_item[posColorHex]' => '#A1B2C3',
            'inventory_item[ledgerAccount]' => '4000',
            'inventory_item[feeAmountCents]' => '500',
            'inventory_item[reorderThresholdUnits]' => '3',
            'inventory_item[trackInventory]' => '1',
        ]);

        $client->submit($form);

        self::assertResponseStatusCodeSame(200);
        self::assertSame(
            'inventoryItemSaved',
            $client->getResponse()->headers->get('HX-Trigger'),
            'Save response must include the inventoryItemSaved trigger header.',
        );

        self::assertSame($beforeListings + 1, $this->countRows('catalog_listings'));
        self::assertSame($beforeItems + 1, $this->countRows('inventory_items'));
    }

    #[Test]
    #[TestDox('Duplicate listing code is rejected with 422 and leaves both tables unchanged.')]
    public function duplicate_code_is_rejected_and_rolls_back_both_writes(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);

        // Seed an existing listing whose code we will try to re-use.
        $this->seedListingAndItem(code: 'DUP-CODE-1');

        $beforeListings = $this->countRows('catalog_listings');
        $beforeItems = $this->countRows('inventory_items');

        $crawler = $client->request('GET', '/admin/inventory/new');
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Create Item')->form([
            'inventory_item[name]' => 'Another Widget',
            'inventory_item[code]' => 'DUP-CODE-1',
            'inventory_item[kind]' => ListingKind::Inventory->value,
            'inventory_item[vendorId]' => '',
            'inventory_item[posColorHex]' => '#A1B2C3',
            'inventory_item[ledgerAccount]' => '4000',
            'inventory_item[feeAmountCents]' => '500',
            'inventory_item[reorderThresholdUnits]' => '0',
            'inventory_item[trackInventory]' => '1',
        ]);

        $client->submit($form);

        self::assertResponseStatusCodeSame(422);
        self::assertSame($beforeListings, $this->countRows('catalog_listings'), 'Listings count must be unchanged.');
        self::assertSame($beforeItems, $this->countRows('inventory_items'), 'Inventory items count must be unchanged.');
    }

    #[Test]
    #[TestDox('Editing an existing item preserves its existing stock batches.')]
    public function edit_does_not_disturb_stock_batches(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);

        // Seed the item with one stock batch of 5 units.
        $this->seedListingAndItem(
            code: 'EDIT-WID-1',
            itemId: self::ITEM_ID,
            listingId: self::LISTING_ID,
            withStock: true,
        );

        $batchesBefore = $this->countRows('inventory_stock_batches');
        $totalBefore = $this->loadTotalOnHand(self::ITEM_ID);
        self::assertSame(5, $totalBefore, 'Pre-condition: seeded item should have 5 units on hand.');

        $crawler = $client->request('GET', '/admin/inventory/' . self::ITEM_ID . '/edit');
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Save Changes')->form([
            'inventory_item[name]' => 'Renamed Widget',
        ]);

        $client->submit($form);

        self::assertResponseStatusCodeSame(200);
        self::assertSame('inventoryItemSaved', $client->getResponse()->headers->get('HX-Trigger'));

        // Stock-preservation contract: the edit path must never touch the
        // StockBatch collection. Counts and totals must match exactly.
        self::assertSame(
            $batchesBefore,
            $this->countRows('inventory_stock_batches'),
            'Stock-batch row count must be unchanged.',
        );
        self::assertSame(5, $this->loadTotalOnHand(self::ITEM_ID), 'On-hand total must remain at 5.');
    }

    #[Test]
    #[TestDox('GET /admin/inventory/{itemId}/barcode returns 200 HTML containing the item code and an SVG marker.')]
    public function barcode_route_renders_code128_print_page(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);

        $this->seedListingAndItem(
            code: 'BAR-WID-1',
            itemId: self::ITEM_ID,
            listingId: self::LISTING_ID,
        );

        $client->request('GET', '/admin/inventory/' . self::ITEM_ID . '/barcode');

        self::assertResponseIsSuccessful();
        $body = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('BAR-WID-1', $body);
        // The CODE128 SVG/HTML adapter emits absolute-positioned bar divs
        // wrapped in an inline <div style="…">; the data-testid label
        // also exposes the human-readable code so screen readers and
        // tests can locate it.
        self::assertStringContainsString('data-testid="barcode-render"', $body);
        self::assertStringContainsString('data-testid="barcode-code"', $body);
    }

    #[Test]
    #[TestDox('Anonymous user gets a redirect or 403 from GET /admin/inventory/new.')]
    public function anonymous_user_is_blocked_from_new(): void
    {
        $client = static::createClient();

        $client->request('GET', '/admin/inventory/new');

        $status = $client->getResponse()->getStatusCode();
        self::assertContains(
            $status,
            [302, 403],
            'Anonymous request to /admin/inventory/new must be denied (3xx redirect to login or 403).',
        );
    }

    private function countRows(string $table): int
    {
        $conn = static::getContainer()->get(Connection::class);
        self::assertInstanceOf(Connection::class, $conn);
        $quoted = $conn->getDatabasePlatform()->quoteSingleIdentifier($table);
        $raw = $conn->fetchOne(sprintf('SELECT COUNT(*) FROM %s', $quoted));
        if (is_int($raw) || is_string($raw)) {
            return (int) $raw;
        }
        self::fail('Unexpected COUNT(*) return shape.');
    }

    private function loadTotalOnHand(string $itemId): int
    {
        $conn = static::getContainer()->get(Connection::class);
        self::assertInstanceOf(Connection::class, $conn);
        $raw = $conn->fetchOne(
            'SELECT COALESCE(SUM(remaining_quantity), 0) FROM inventory_stock_batches WHERE item_id = :id',
            ['id' => $itemId],
        );
        if (is_int($raw) || is_string($raw)) {
            return (int) $raw;
        }
        self::fail('Unexpected SUM return shape.');
    }

    private function seedListingAndItem(
        string $code,
        string $itemId = self::ITEM_ID,
        string $listingId = self::LISTING_ID,
        bool $withStock = false,
    ): void {
        $listings = static::getContainer()->get(Listings::class);
        self::assertInstanceOf(Listings::class, $listings);
        $items = static::getContainer()->get(InventoryItems::class);
        self::assertInstanceOf(InventoryItems::class, $items);
        $clock = new MockClock(new DateTimeImmutable('2026-05-27 12:00:00'));

        $listing = Listing::register(
            ListingId::fromString($listingId),
            ListingCode::of($code),
            ListingKind::Inventory,
            'Seed ' . $code,
            [],
            TaxTreatment::none(),
            LedgerAccount::of('4000'),
            $clock,
        );
        $listing->releaseEvents();
        $listings->add($listing);

        $item = InventoryItem::register(
            InventoryItemId::fromString($itemId),
            ListingId::fromString($listingId),
            null,
            PosColor::default(),
            true,
            false,
            ReorderThreshold::ofUnits(0),
            $clock,
        );

        if ($withStock) {
            $item->receiveBatch(
                FacilityCode::fromString(self::FACILITY),
                Quantity::ofUnits(5),
                CostPerUnit::ofCents(100),
                null,
                null,
                StockBatchId::fromString(self::BATCH_ID),
                $clock,
            );
        }

        $item->releaseEvents();
        $items->add($item);
    }
}
