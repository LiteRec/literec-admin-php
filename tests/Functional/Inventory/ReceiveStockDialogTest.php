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
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\MockClock;

/**
 * Drives the LRA-87 Receive Stock HTMX dialog end-to-end. DAMA wraps
 * each test in a transaction rolled back at teardown so seeded rows
 * and writes stay isolated.
 */
#[Large]
#[Group('database')]
final class ReceiveStockDialogTest extends WebTestCase
{
    use SignsInUsers;

    private const string ROUTE_RECEIVE = '/admin/inventory/%s/receive';

    private const string TEST_USERNAME = 'receive_stock_e2e';
    // NOSONAR — test fixture, not a real credential.
    private const string TEST_PASSWORD = 'CorrectHorseBattery!'; // NOSONAR

    private const string ITEM_ID = '019571bf-5d51-7000-b500-00000000ef01';
    private const string LISTING_ID = '019571bf-5d51-7000-b500-00000000ef02';
    private const string BATCH_ID = '019571bf-5d51-7000-b500-00000000ef03';
    private const string FACILITY = 'MAIN';
    private const string SUBMIT_BUTTON = 'Record Receipt';

    #[Test]
    #[TestDox('GET /admin/inventory/{itemId}/receive returns a dialog with the form and facility options.')]
    public function get_dialog_returns_form_with_facility_options(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);

        $this->seedItemWithStock();

        $client->request('GET', sprintf(self::ROUTE_RECEIVE, self::ITEM_ID));

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('div[role="dialog"][aria-modal="true"]');
        self::assertSelectorExists('select[name="receive_stock[facilityCode]"]');
        self::assertSelectorExists('input[name="receive_stock[_token]"]');
    }

    #[Test]
    #[TestDox('POST valid (cost per unit only) returns 200 with HX-Trigger and persists a new stock batch.')]
    public function post_valid_per_unit_cost_creates_batch(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);
        $this->seedItemWithStock();

        $beforeBatches = $this->countBatches(self::ITEM_ID);
        $beforeUnits = $this->loadTotalOnHand(self::ITEM_ID);

        $crawler = $client->request('GET', sprintf(self::ROUTE_RECEIVE, self::ITEM_ID));
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton(self::SUBMIT_BUTTON)->form([
            'receive_stock[facilityCode]' => self::FACILITY,
            'receive_stock[quantityUnits]' => '7',
            'receive_stock[costPerUnitCents]' => '250',
            'receive_stock[totalCostCents]' => '',
            'receive_stock[comment]' => 'top up',
        ]);
        $client->submit($form);

        self::assertResponseStatusCodeSame(200);
        self::assertSame(
            'stockReceived',
            $client->getResponse()->headers->get('HX-Trigger'),
            'Save response must include the stockReceived trigger header.',
        );
        self::assertSame($beforeBatches + 1, $this->countBatches(self::ITEM_ID));
        self::assertSame($beforeUnits + 7, $this->loadTotalOnHand(self::ITEM_ID));
        self::assertSame(250, $this->newestBatchCostPerUnit(self::ITEM_ID));
    }

    #[Test]
    #[TestDox('POST with both per-unit AND total cost returns 422 and flags both inputs.')]
    public function post_with_both_costs_is_rejected(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);
        $this->seedItemWithStock();

        $crawler = $client->request('GET', sprintf(self::ROUTE_RECEIVE, self::ITEM_ID));
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton(self::SUBMIT_BUTTON)->form([
            'receive_stock[facilityCode]' => self::FACILITY,
            'receive_stock[quantityUnits]' => '5',
            'receive_stock[costPerUnitCents]' => '100',
            'receive_stock[totalCostCents]' => '500',
        ]);
        $client->submit($form);

        self::assertResponseStatusCodeSame(422);
        $body = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('Provide a per-unit cost or a total cost', $body);
    }

    #[Test]
    #[TestDox('POST with only a total cost derives per-unit via intdiv when the division is exact (zero remainder).')]
    public function post_with_only_total_cost_derives_per_unit_evenly(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);
        $this->seedItemWithStock();

        $crawler = $client->request('GET', sprintf(self::ROUTE_RECEIVE, self::ITEM_ID));
        self::assertResponseIsSuccessful();

        // 5 units @ 750 total = 150 per unit, 0 remainder (clean case
        // per the ticket spec — exercises the intdiv path without
        // exercising the "(N cent remainder)" comment suffix because
        // the ticket explicitly calls out the 750/5=150 expectation).
        $form = $crawler->selectButton(self::SUBMIT_BUTTON)->form([
            'receive_stock[facilityCode]' => self::FACILITY,
            'receive_stock[quantityUnits]' => '5',
            'receive_stock[costPerUnitCents]' => '',
            'receive_stock[totalCostCents]' => '750',
            'receive_stock[comment]' => '',
        ]);
        $client->submit($form);

        self::assertResponseStatusCodeSame(200);
        self::assertSame(150, $this->newestBatchCostPerUnit(self::ITEM_ID));
    }

    #[Test]
    #[TestDox('POST with a non-divisible total cost records the remainder onto the comment.')]
    public function post_with_remainder_total_cost_records_remainder_note(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);
        $this->seedItemWithStock();

        $crawler = $client->request('GET', sprintf(self::ROUTE_RECEIVE, self::ITEM_ID));
        self::assertResponseIsSuccessful();

        // 3 units @ 100 total = 33 per unit, 1 remainder.
        $form = $crawler->selectButton(self::SUBMIT_BUTTON)->form([
            'receive_stock[facilityCode]' => self::FACILITY,
            'receive_stock[quantityUnits]' => '3',
            'receive_stock[costPerUnitCents]' => '',
            'receive_stock[totalCostCents]' => '100',
            'receive_stock[comment]' => '',
        ]);
        $client->submit($form);

        self::assertResponseStatusCodeSame(200);
        self::assertSame(33, $this->newestBatchCostPerUnit(self::ITEM_ID));
        self::assertStringContainsString('(1 cent remainder)', (string) $this->newestBatchComment(self::ITEM_ID));
    }

    #[Test]
    #[TestDox('A multi-cent intdiv remainder pluralises the cent suffix on the comment.')]
    public function post_with_multi_cent_remainder_pluralizes_correctly(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);
        $this->seedItemWithStock();

        $crawler = $client->request('GET', sprintf(self::ROUTE_RECEIVE, self::ITEM_ID));
        self::assertResponseIsSuccessful();

        // 5 units @ 103 total = 20 per unit, 3 remainder.
        $form = $crawler->selectButton(self::SUBMIT_BUTTON)->form([
            'receive_stock[facilityCode]' => self::FACILITY,
            'receive_stock[quantityUnits]' => '5',
            'receive_stock[costPerUnitCents]' => '',
            'receive_stock[totalCostCents]' => '103',
            'receive_stock[comment]' => '',
        ]);
        $client->submit($form);

        self::assertResponseStatusCodeSame(200);
        self::assertSame(20, $this->newestBatchCostPerUnit(self::ITEM_ID));
        self::assertStringContainsString('(3 cents remainder)', (string) $this->newestBatchComment(self::ITEM_ID));
    }

    #[Test]
    #[TestDox('POST without a facility returns 422.')]
    public function post_without_facility_is_rejected(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);
        $this->seedItemWithStock();

        $crawler = $client->request('GET', sprintf(self::ROUTE_RECEIVE, self::ITEM_ID));
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton(self::SUBMIT_BUTTON)->form([
            'receive_stock[facilityCode]' => '',
            'receive_stock[quantityUnits]' => '1',
            'receive_stock[costPerUnitCents]' => '100',
        ]);
        $client->submit($form);

        self::assertResponseStatusCodeSame(422);
    }

    #[Test]
    #[TestDox('Anonymous user is blocked from GET /admin/inventory/{itemId}/receive.')]
    public function anonymous_is_blocked(): void
    {
        $client = static::createClient();

        $client->request('GET', sprintf(self::ROUTE_RECEIVE, self::ITEM_ID));

        $status = $client->getResponse()->getStatusCode();
        self::assertContains(
            $status,
            [302, 403],
            'Anonymous request must be denied (3xx redirect or 403).',
        );
    }

    private function countBatches(string $itemId): int
    {
        $conn = static::getContainer()->get(Connection::class);
        self::assertInstanceOf(Connection::class, $conn);
        $raw = $conn->fetchOne(
            'SELECT COUNT(*) FROM inventory_stock_batches WHERE item_id = :id',
            ['id' => $itemId],
        );
        if (is_int($raw) || is_string($raw)) {
            return (int) $raw;
        }
        self::fail('Unexpected COUNT(*) shape.');
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

    private function newestBatchCostPerUnit(string $itemId): int
    {
        $conn = static::getContainer()->get(Connection::class);
        self::assertInstanceOf(Connection::class, $conn);
        $raw = $conn->fetchOne(
            'SELECT cost_per_unit_cents FROM inventory_stock_batches '
            . 'WHERE item_id = :id ORDER BY received_at DESC, id DESC LIMIT 1',
            ['id' => $itemId],
        );
        if (is_int($raw) || is_string($raw)) {
            return (int) $raw;
        }
        self::fail('Unexpected cost_per_unit_cents shape.');
    }

    private function newestBatchComment(string $itemId): ?string
    {
        $conn = static::getContainer()->get(Connection::class);
        self::assertInstanceOf(Connection::class, $conn);
        $raw = $conn->fetchOne(
            'SELECT comments FROM inventory_stock_batches '
            . 'WHERE item_id = :id ORDER BY received_at DESC, id DESC LIMIT 1',
            ['id' => $itemId],
        );
        if ($raw === null || $raw === false) {
            return null;
        }
        if (is_string($raw)) {
            return $raw;
        }
        self::fail('Unexpected comments shape.');
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
            ListingCode::of('RCV-WID-1'),
            ListingKind::Inventory,
            'Receive Widget',
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
