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
use App\Inventory\Domain\ValueObject\InventoryItemId;
use App\Inventory\Domain\ValueObject\PosColor;
use App\Inventory\Domain\ValueObject\ReorderThreshold;
use App\Tests\Support\Trait\SignsInUsers;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\MockClock;

/**
 * Drives the Inventory list page (LRA-85) through the real container,
 * the real Doctrine read model, and freshly-seeded Catalog Listings +
 * InventoryItem aggregates.
 *
 * DAMA's PHPUnit extension wraps each test in a transaction rolled back at
 * teardown, so seeded rows are isolated between tests without manual
 * truncation. Identifiers use UUID v7 values so they sort deterministically
 * across runs.
 */
#[Large]
#[Group('database')]
final class ListInventoryControllerTest extends WebTestCase
{
    use SignsInUsers;

    private const string TEST_USERNAME = 'inventory_list_e2e';
    // NOSONAR — test fixture, not a real credential.
    private const string TEST_PASSWORD = 'CorrectHorseBattery!'; // NOSONAR

    private const string ITEM_A    = '019571bf-5d51-7000-b500-00000000ee01';
    private const string ITEM_B    = '019571bf-5d51-7000-b500-00000000ee02';
    private const string ITEM_C    = '019571bf-5d51-7000-b500-00000000ee03';
    private const string LISTING_A = '019571bf-5d51-7000-b500-00000000ef01';
    private const string LISTING_B = '019571bf-5d51-7000-b500-00000000ef02';
    private const string LISTING_C = '019571bf-5d51-7000-b500-00000000ef03';

    private MockClock $clock;

    protected function setUp(): void
    {
        $this->clock = new MockClock(new DateTimeImmutable('2026-05-25 12:00:00'));
    }

    #[Test]
    #[TestDox('GET /admin/inventory renders 200 with a row per seeded item.')]
    public function get_inventory_index_returns_200_and_shows_seeded_rows(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);
        $this->seedThreeItems();

        $client->request('GET', '/admin/inventory');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main h1', 'Inventory');
        self::assertSelectorExists(sprintf('tr[data-testid="inventory-row-%s"]', self::ITEM_A));
        self::assertSelectorExists(sprintf('tr[data-testid="inventory-row-%s"]', self::ITEM_B));
        self::assertSelectorExists(sprintf('tr[data-testid="inventory-row-%s"]', self::ITEM_C));
        // Inventory lives under the Cash Register top-level nav item; the
        // top-level item is what receives the active highlight, while the
        // Inventory anchor itself appears inside its dropdown sub-menu.
        self::assertSelectorExists(
            'nav[aria-label="Main navigation"] [role="menuitem"][href="/cash-register"].bg-litrec-primary',
        );
        self::assertSelectorExists(
            'nav[aria-label="Main navigation"] [role="menuitem"][href="/admin/inventory"]',
        );
    }

    #[Test]
    #[TestDox('GET /admin/inventory/_table?search=Alpha returns a partial containing only matching rows.')]
    public function filter_by_search_via_partial_route(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);
        $this->seedThreeItems();

        $client->request('GET', '/admin/inventory/_table?search=Alpha');

        self::assertResponseIsSuccessful();
        $response = $client->getResponse();
        self::assertStringContainsString('text/html', (string) $response->headers->get('Content-Type'));
        $body = (string) $response->getContent();
        self::assertSame(
            0,
            preg_match('/<!doctype\b|<html\b/i', $body),
            'Partial response must not include the full HTML shell.',
        );

        // ITEM_A (name "Alpha Widget") matches; ITEM_B (Beta) and ITEM_C (Gamma) do not.
        self::assertStringContainsString('inventory-row-' . self::ITEM_A, $body);
        self::assertStringNotContainsString('inventory-row-' . self::ITEM_B, $body);
        self::assertStringNotContainsString('inventory-row-' . self::ITEM_C, $body);
    }

    #[Test]
    #[TestDox('With no seeded data the page renders the empty state message.')]
    public function empty_filters_with_no_data_shows_empty_state(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);

        $client->request('GET', '/admin/inventory');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', 'No items match your filters.');
    }

    #[Test]
    #[TestDox('Authenticated user sees the manage-inventory action buttons.')]
    public function shows_action_buttons_for_authenticated_user(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);

        $client->request('GET', '/admin/inventory');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-testid="open-new-inventory-item"]');
    }

    private function seedThreeItems(): void
    {
        $listings = static::getContainer()->get(Listings::class);
        self::assertInstanceOf(Listings::class, $listings);

        $items = static::getContainer()->get(InventoryItems::class);
        self::assertInstanceOf(InventoryItems::class, $items);

        $this->seedItem($listings, $items, self::LISTING_A, 'ITEM-A', 'Alpha Widget', self::ITEM_A);
        $this->seedItem($listings, $items, self::LISTING_B, 'ITEM-B', 'Beta Sprocket', self::ITEM_B);
        $this->seedItem($listings, $items, self::LISTING_C, 'ITEM-C', 'Gamma Gadget', self::ITEM_C);
    }

    private function seedItem(
        Listings $listings,
        InventoryItems $items,
        string $listingId,
        string $code,
        string $name,
        string $itemId,
    ): void {
        $listing = Listing::register(
            ListingId::fromString($listingId),
            ListingCode::of($code),
            ListingKind::Inventory,
            $name,
            [],
            TaxTreatment::none(),
            LedgerAccount::of('4000'),
            $this->clock,
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
            ReorderThreshold::none(),
            $this->clock,
        );
        $item->releaseEvents();
        $items->add($item);
    }
}
