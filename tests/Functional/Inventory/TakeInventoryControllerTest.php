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
use App\Inventory\Domain\ValueObject\StockAdjustmentReason;
use App\Inventory\Domain\ValueObject\StockBatchId;
use App\Tests\Support\Trait\SignsInUsers;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\MockClock;

/**
 * Drives the LRA-87 Take Inventory bulk grid end-to-end. DAMA wraps
 * each test in a transaction rolled back at teardown so seeded rows
 * and writes stay isolated.
 */
#[Large]
#[Group('database')]
final class TakeInventoryControllerTest extends WebTestCase
{
    use SignsInUsers;

    private const string ROUTE_TAKE_INDEX = '/admin/inventory/take';
    private const string ROUTE_TAKE_INDEX_WITH_FACILITY = '/admin/inventory/take?facilityCode=MAIN';

    private const string TEST_USERNAME = 'take_inventory_e2e';
    // NOSONAR — test fixture, not a real credential.
    private const string TEST_PASSWORD = 'CorrectHorseBattery!'; // NOSONAR

    private const string ITEM_ID = '019571bf-5d51-7000-b500-00000000ee01';
    private const string LISTING_ID = '019571bf-5d51-7000-b500-00000000ee02';
    private const string BATCH_ID = '019571bf-5d51-7000-b500-00000000ee03';
    private const string FACILITY = 'MAIN';
    private const string LISTING_CODE = 'TAKE-WID-1';
    private const string SUBMIT_BUTTON = 'Post Adjustments';

    #[Test]
    #[TestDox('GET /admin/inventory/take without a facility shows the picker landing.')]
    public function get_without_facility_shows_picker(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);

        $client->request('GET', self::ROUTE_TAKE_INDEX);

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('input[name="facilityCode"]');
        self::assertSelectorExists('button[data-testid="take-inventory-pick-facility"]');
    }

    #[Test]
    #[TestDox('GET /admin/inventory/take?facilityCode=MAIN renders a row per seeded item.')]
    public function get_with_facility_renders_one_row_per_seeded_item(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);
        $this->seedItemWithStock();

        $client->request('GET', self::ROUTE_TAKE_INDEX_WITH_FACILITY);

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form#take-inventory-grid');
        self::assertSelectorExists('[data-testid="take-row-0"]');
        self::assertSelectorExists('[data-testid="row-0-actual"]');
    }

    #[Test]
    #[TestDox('Posting a variance with a reason returns 200 + HX-Trigger and reduces the on-hand quantity.')]
    public function post_variance_with_reason_succeeds(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);
        $this->seedItemWithStock();

        self::assertSame(5, $this->loadTotalOnHand(self::ITEM_ID), 'Pre-condition: seeded item has 5 units.');

        $crawler = $client->request('GET', self::ROUTE_TAKE_INDEX_WITH_FACILITY);
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton(self::SUBMIT_BUTTON)->form([
            'take_inventory[lines][0][actual]' => '3',
            'take_inventory[lines][0][reason]' => StockAdjustmentReason::DAMAGED->value,
            'take_inventory[lines][0][reasonNote]' => 'pallet drop',
        ]);
        $client->submit($form);

        self::assertResponseStatusCodeSame(200);
        self::assertSame('stockAdjusted', $client->getResponse()->headers->get('HX-Trigger'));
        self::assertSame(
            3,
            $this->loadTotalOnHand(self::ITEM_ID),
            'Stock-batch on-hand should reflect the variance from 5 to 3.',
        );
    }

    #[Test]
    #[TestDox('Posting a variance without a reason rejects the whole submit atomically and leaves stock unchanged.')]
    public function post_variance_without_reason_rejects_atomically(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);
        $this->seedItemWithStock();

        $crawler = $client->request('GET', self::ROUTE_TAKE_INDEX_WITH_FACILITY);
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton(self::SUBMIT_BUTTON)->form([
            'take_inventory[lines][0][actual]' => '2',
            'take_inventory[lines][0][reason]' => '',
            'take_inventory[lines][0][reasonNote]' => '',
        ]);
        $client->submit($form);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(
            5,
            $this->loadTotalOnHand(self::ITEM_ID),
            'Atomic reject must not consume any stock.',
        );
    }

    #[Test]
    #[TestDox('Posting with no variances at all returns 200 + HX-Trigger and leaves stock unchanged.')]
    public function post_with_no_variances_changes_nothing(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);
        $this->seedItemWithStock();

        $crawler = $client->request('GET', self::ROUTE_TAKE_INDEX_WITH_FACILITY);
        self::assertResponseIsSuccessful();

        // Leave actual at its rendered default (== expected) so no row is a variance.
        $form = $crawler->selectButton(self::SUBMIT_BUTTON)->form();
        $client->submit($form);

        self::assertResponseStatusCodeSame(200);
        self::assertSame('stockAdjusted', $client->getResponse()->headers->get('HX-Trigger'));
        self::assertSame(
            5,
            $this->loadTotalOnHand(self::ITEM_ID),
            'No-variance submit must not consume any stock.',
        );
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
        self::fail('Unexpected SUM shape.');
    }

    private function seedItemWithStock(): void
    {
        $listings = static::getContainer()->get(Listings::class);
        self::assertInstanceOf(Listings::class, $listings);
        $items = static::getContainer()->get(InventoryItems::class);
        self::assertInstanceOf(InventoryItems::class, $items);
        $clock = new MockClock(new DateTimeImmutable('2026-05-27 12:00:00'));

        $listing = Listing::register(
            ListingId::fromString(self::LISTING_ID),
            ListingCode::of(self::LISTING_CODE),
            ListingKind::Inventory,
            'Take Widget',
            [],
            TaxTreatment::none(),
            LedgerAccount::of('4000'),
            $clock,
        );
        $listing->releaseEvents();
        $listings->add($listing);

        $item = InventoryItem::register(
            InventoryItemId::fromString(self::ITEM_ID),
            ListingId::fromString(self::LISTING_ID),
            null,
            PosColor::default(),
            true,
            false,
            ReorderThreshold::ofUnits(0),
            $clock,
        );
        $item->receiveBatch(
            FacilityCode::fromString(self::FACILITY),
            Quantity::ofUnits(5),
            CostPerUnit::ofCents(100),
            null,
            null,
            StockBatchId::fromString(self::BATCH_ID),
            $clock,
        );
        $item->releaseEvents();
        $items->add($item);
    }
}
