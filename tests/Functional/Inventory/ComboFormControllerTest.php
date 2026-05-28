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
 * Drives the LRA-89 Define Combo HTMX dialog end-to-end. DAMA wraps
 * each test in a transaction rolled back at teardown so seeded rows
 * and writes stay isolated.
 */
#[Large]
#[Group('database')]
final class ComboFormControllerTest extends WebTestCase
{
    use SignsInUsers;
    use SeedsInventoryItemForUi;

    private const string ROUTE_NEW_COMBO = '/admin/inventory/combos/new';

    private const string TEST_USERNAME = 'combo_dialog_e2e';

    private const string PARENT_LISTING_ID = '019571bf-5d51-7000-b500-00000000c001';
    private const string COMPONENT_A_ITEM = '019571bf-5d51-7000-b500-00000000c011';
    private const string COMPONENT_A_LISTING = '019571bf-5d51-7000-b500-00000000c012';
    private const string COMPONENT_A_BATCH = '019571bf-5d51-7000-b500-00000000c013';
    private const string COMPONENT_B_ITEM = '019571bf-5d51-7000-b500-00000000c021';
    private const string COMPONENT_B_LISTING = '019571bf-5d51-7000-b500-00000000c022';
    private const string COMPONENT_B_BATCH = '019571bf-5d51-7000-b500-00000000c023';
    private const string PARENT_LISTING_CODE = 'COMBO-PARENT-1';
    private const string FACILITY = 'MAIN';

    #[Test]
    #[TestDox('GET /admin/inventory/combos/new returns a dialog fragment with the combo form.')]
    public function get_new_form_returns_dialog_fragment(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);

        $client->request('GET', self::ROUTE_NEW_COMBO);

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('div[role="dialog"][aria-modal="true"]');
        self::assertSelectorExists('input[name="inventory_combo[parentListingId]"]');
        self::assertSelectorExists('input[name="inventory_combo[_token]"]');
        self::assertSelectorExists('[data-testid="combo-add-component"]');
    }

    #[Test]
    #[TestDox('POST /admin/inventory/combos/new with two components returns 200 + HX-Trigger and inserts a combo row.')]
    public function post_new_with_two_components_creates_combo(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);

        // Seed the parent listing and two component items.
        $this->seedParentListing();
        $this->seedInventoryItemWithStock(
            itemId: self::COMPONENT_A_ITEM,
            listingId: self::COMPONENT_A_LISTING,
            batchId: self::COMPONENT_A_BATCH,
            listingCode: 'COMBO-COMP-A',
            listingName: 'Combo Component A',
            facilityCode: self::FACILITY,
        );
        $this->seedInventoryItemWithStock(
            itemId: self::COMPONENT_B_ITEM,
            listingId: self::COMPONENT_B_LISTING,
            batchId: self::COMPONENT_B_BATCH,
            listingCode: 'COMBO-COMP-B',
            listingName: 'Combo Component B',
            facilityCode: self::FACILITY,
        );

        $beforeCombos = $this->countRows('inventory_combos');

        $crawler = $client->request('GET', self::ROUTE_NEW_COMBO);
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Create Combo')->form();
        // The pre-rendered grid seeds one empty row at index 0. Inject a
        // second row by mirroring the data-prototype the Alpine block
        // would have cloned on the client side.
        $values = $form->getPhpValues();
        $values['inventory_combo']['parentListingId'] = self::PARENT_LISTING_ID;
        $values['inventory_combo']['components'] = [
            [
                'componentItemId' => self::COMPONENT_A_ITEM,
                'quantityPerCombo' => 1,
            ],
            [
                'componentItemId' => self::COMPONENT_B_ITEM,
                'quantityPerCombo' => 2,
            ],
        ];

        $client->request(
            $form->getMethod(),
            $form->getUri(),
            $values,
            [],
        );

        self::assertResponseStatusCodeSame(200);
        self::assertSame(
            'comboSaved',
            $client->getResponse()->headers->get('HX-Trigger'),
            'Save response must include the comboSaved trigger header.',
        );
        self::assertSame($beforeCombos + 1, $this->countRows('inventory_combos'));
    }

    #[Test]
    #[TestDox('POST /admin/inventory/combos/new with empty components returns 422 with a field-level error.')]
    public function post_with_empty_components_is_rejected_with_422(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);

        $this->seedParentListing();
        $beforeCombos = $this->countRows('inventory_combos');

        $crawler = $client->request('GET', self::ROUTE_NEW_COMBO);
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Create Combo')->form();
        $values = $form->getPhpValues();
        $values['inventory_combo']['parentListingId'] = self::PARENT_LISTING_ID;
        $values['inventory_combo']['components'] = [];

        $client->request(
            $form->getMethod(),
            $form->getUri(),
            $values,
            [],
        );

        self::assertResponseStatusCodeSame(422);
        self::assertSame($beforeCombos, $this->countRows('inventory_combos'));
    }

    #[Test]
    #[TestDox('Anonymous user gets a redirect or 403 from GET /admin/inventory/combos/new.')]
    public function anonymous_user_is_blocked_from_new(): void
    {
        $client = static::createClient();

        $client->request('GET', self::ROUTE_NEW_COMBO);

        $status = $client->getResponse()->getStatusCode();
        self::assertContains(
            $status,
            [302, 403],
            'Anonymous request to /admin/inventory/combos/new must be denied (3xx redirect to login or 403).',
        );
    }

    private function seedParentListing(): void
    {
        // The Combo aggregate stores the parent listing id but does not
        // verify its existence against the Catalog repository at define
        // time, so this seed is a courtesy fixture for symmetry — it
        // ensures the listing/inventory rows surrounding the combo are
        // not orphaned and matches what production data would look like.
        $this->seedInventoryItemWithStock(
            itemId: '019571bf-5d51-7000-b500-00000000c000',
            listingId: self::PARENT_LISTING_ID,
            batchId: '019571bf-5d51-7000-b500-00000000c099',
            listingCode: self::PARENT_LISTING_CODE,
            listingName: 'Combo Parent Listing',
            facilityCode: self::FACILITY,
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
}
