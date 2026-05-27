<?php

declare(strict_types=1);

namespace App\Tests\Functional\Inventory;

use App\Tests\Support\Trait\SeedsInventoryItemForUi;
use App\Tests\Support\Trait\SignsInUsers;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Drives the LRA-89 Link Item HTMX dialog end-to-end. DAMA wraps each
 * test in a transaction rolled back at teardown so seeded rows and
 * writes stay isolated.
 */
#[Large]
#[Group('database')]
final class ItemLinkFormControllerTest extends WebTestCase
{
    use SignsInUsers;
    use SeedsInventoryItemForUi;

    private const string ROUTE_NEW_LINK_TEMPLATE = '/admin/inventory/%s/links/new';
    private const string ROUTE_UNLINK_TEMPLATE = '/admin/inventory/%s/links/%s';

    private const string TEST_USERNAME = 'link_dialog_e2e';
    // NOSONAR — test fixture, not a real credential.
    private const string TEST_PASSWORD = 'CorrectHorseBattery!'; // NOSONAR

    private const string ITEM_A_ID = '019571bf-5d51-7000-b500-00000000d001';
    private const string ITEM_A_LISTING = '019571bf-5d51-7000-b500-00000000d002';
    private const string ITEM_A_BATCH = '019571bf-5d51-7000-b500-00000000d003';
    private const string ITEM_B_ID = '019571bf-5d51-7000-b500-00000000d011';
    private const string ITEM_B_LISTING = '019571bf-5d51-7000-b500-00000000d012';
    private const string ITEM_B_BATCH = '019571bf-5d51-7000-b500-00000000d013';
    private const string FACILITY = 'MAIN';

    #[Test]
    #[TestDox('GET /admin/inventory/{itemId}/links/new returns a dialog fragment with the link form.')]
    public function get_new_form_returns_dialog_fragment(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);

        $this->seedPair();

        $client->request('GET', sprintf(self::ROUTE_NEW_LINK_TEMPLATE, self::ITEM_A_ID));

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('div[role="dialog"][aria-modal="true"]');
        self::assertSelectorExists('input[name="inventory_item_link[linkedItemId]"]');
        self::assertSelectorExists('input[name="inventory_item_link[_token]"]');
    }

    #[Test]
    #[TestDox('POST with valid link payload returns 200 + HX-Trigger and inserts a row in inventory_item_links.')]
    public function post_with_valid_payload_creates_link(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);

        $this->seedPair();
        $beforeLinks = $this->countRows('inventory_item_links');

        $crawler = $client->request('GET', sprintf(self::ROUTE_NEW_LINK_TEMPLATE, self::ITEM_A_ID));
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Create Link')->form([
            'inventory_item_link[linkedItemId]' => self::ITEM_B_ID,
            'inventory_item_link[reservedQuantityUnits]' => '3',
            'inventory_item_link[minRequiredUnits]' => '0',
            'inventory_item_link[maxPerPurchaseUnits]' => '0',
            'inventory_item_link[includeUntilIso]' => '',
        ]);

        $client->submit($form);

        self::assertResponseStatusCodeSame(200);
        self::assertSame(
            'linkSaved',
            $client->getResponse()->headers->get('HX-Trigger'),
            'Save response must include the linkSaved trigger header.',
        );
        self::assertSame($beforeLinks + 1, $this->countRows('inventory_item_links'));
    }

    #[Test]
    #[TestDox('POST with reservedQuantityUnits=-1 returns 422 with a field-level error.')]
    public function post_with_negative_reserved_qty_is_rejected_with_422(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);

        $this->seedPair();
        $beforeLinks = $this->countRows('inventory_item_links');

        $crawler = $client->request('GET', sprintf(self::ROUTE_NEW_LINK_TEMPLATE, self::ITEM_A_ID));
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Create Link')->form([
            'inventory_item_link[linkedItemId]' => self::ITEM_B_ID,
            'inventory_item_link[reservedQuantityUnits]' => '-1',
            'inventory_item_link[minRequiredUnits]' => '0',
            'inventory_item_link[maxPerPurchaseUnits]' => '0',
            'inventory_item_link[includeUntilIso]' => '',
        ]);

        $client->submit($form);

        self::assertResponseStatusCodeSame(422);
        // Symfony GreaterThanOrEqual(0) renders the violation message
        // inline next to the input (field-level, not form-level).
        $body = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('should be greater than or equal', $body);
        self::assertSame($beforeLinks, $this->countRows('inventory_item_links'));
    }

    #[Test]
    #[TestDox('DELETE /admin/inventory/{itemId}/links/{linkId} removes the link row and returns 200 + HX-Trigger.')]
    public function delete_unlink_removes_row(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);

        $this->seedPair();

        // Create a link via the create endpoint, then DELETE it.
        $crawler = $client->request('GET', sprintf(self::ROUTE_NEW_LINK_TEMPLATE, self::ITEM_A_ID));
        self::assertResponseIsSuccessful();
        $form = $crawler->selectButton('Create Link')->form([
            'inventory_item_link[linkedItemId]' => self::ITEM_B_ID,
            'inventory_item_link[reservedQuantityUnits]' => '2',
            'inventory_item_link[minRequiredUnits]' => '0',
            'inventory_item_link[maxPerPurchaseUnits]' => '0',
            'inventory_item_link[includeUntilIso]' => '',
        ]);
        $client->submit($form);
        self::assertResponseStatusCodeSame(200);

        $linkId = $this->fetchLinkIdForPair(self::ITEM_A_ID, self::ITEM_B_ID);
        $beforeLinks = $this->countRows('inventory_item_links');

        $client->request('DELETE', sprintf(self::ROUTE_UNLINK_TEMPLATE, self::ITEM_A_ID, $linkId));

        self::assertResponseStatusCodeSame(200);
        self::assertSame(
            'linkSaved',
            $client->getResponse()->headers->get('HX-Trigger'),
            'Unlink response must include the linkSaved trigger header.',
        );
        self::assertSame($beforeLinks - 1, $this->countRows('inventory_item_links'));
    }

    #[Test]
    #[TestDox('Anonymous user gets a redirect or 403 from GET /admin/inventory/{itemId}/links/new.')]
    public function anonymous_user_is_blocked_from_new(): void
    {
        $client = static::createClient();

        $client->request('GET', sprintf(self::ROUTE_NEW_LINK_TEMPLATE, self::ITEM_A_ID));

        $status = $client->getResponse()->getStatusCode();
        self::assertContains(
            $status,
            [302, 403],
            'Anonymous request to /admin/inventory/{itemId}/links/new must be denied (3xx redirect to login or 403).',
        );
    }

    private function seedPair(): void
    {
        $this->seedInventoryItemWithStock(
            itemId: self::ITEM_A_ID,
            listingId: self::ITEM_A_LISTING,
            batchId: self::ITEM_A_BATCH,
            listingCode: 'LINK-A-1',
            listingName: 'Link Item A',
            facilityCode: self::FACILITY,
        );
        $this->seedInventoryItemWithStock(
            itemId: self::ITEM_B_ID,
            listingId: self::ITEM_B_LISTING,
            batchId: self::ITEM_B_BATCH,
            listingCode: 'LINK-B-1',
            listingName: 'Link Item B',
            facilityCode: self::FACILITY,
        );
    }

    private function fetchLinkIdForPair(string $masterItemId, string $linkedItemId): string
    {
        $conn = static::getContainer()->get(Connection::class);
        self::assertInstanceOf(Connection::class, $conn);
        $raw = $conn->fetchOne(
            'SELECT id FROM inventory_item_links '
            . 'WHERE master_item_id = :master AND linked_item_id = :linked',
            ['master' => $masterItemId, 'linked' => $linkedItemId],
        );
        self::assertIsString($raw, 'Expected a single matching link id row.');
        return $raw;
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
}
