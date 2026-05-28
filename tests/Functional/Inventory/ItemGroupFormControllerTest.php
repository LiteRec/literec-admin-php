<?php

declare(strict_types=1);

namespace App\Tests\Functional\Inventory;

use App\Tests\Support\Trait\SignsInUsers;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Drives the LRA-89 Create Item Group HTMX dialog end-to-end. DAMA
 * wraps each test in a transaction rolled back at teardown so seeded
 * rows and writes stay isolated.
 */
#[Large]
#[Group('database')]
final class ItemGroupFormControllerTest extends WebTestCase
{
    use SignsInUsers;

    private const string ROUTE_NEW_GROUP = '/admin/inventory/groups/new';

    private const string TEST_USERNAME = 'group_dialog_e2e';

    #[Test]
    #[TestDox('GET /admin/inventory/groups/new returns a dialog fragment with the group form.')]
    public function get_new_form_returns_dialog_fragment(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);

        $client->request('GET', self::ROUTE_NEW_GROUP);

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('div[role="dialog"][aria-modal="true"]');
        self::assertSelectorExists('input[name="inventory_item_group[name]"]');
        self::assertSelectorExists('input[name="inventory_item_group[_token]"]');
    }

    #[Test]
    #[TestDox('POST /admin/inventory/groups/new with a valid name/color returns 200 + HX-Trigger and inserts a row.')]
    public function post_new_with_valid_payload_creates_group(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);

        $beforeGroups = $this->countRows('inventory_item_groups');

        $crawler = $client->request('GET', self::ROUTE_NEW_GROUP);
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Create Group')->form([
            'inventory_item_group[name]' => 'Group ' . substr(bin2hex(random_bytes(3)), 0, 5),
            'inventory_item_group[colorHex]' => '#A1B2C3',
            'inventory_item_group[scope]' => 'all',
            'inventory_item_group[facilityCode]' => '',
        ]);

        $client->submit($form);

        self::assertResponseStatusCodeSame(200);
        self::assertSame(
            'groupSaved',
            $client->getResponse()->headers->get('HX-Trigger'),
            'Save response must include the groupSaved trigger header.',
        );
        self::assertSame($beforeGroups + 1, $this->countRows('inventory_item_groups'));
    }

    #[Test]
    #[TestDox('POST with scope=facility but blank facilityCode returns 422 with a field-level error.')]
    public function post_facility_scope_without_code_is_rejected_with_422(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);

        $beforeGroups = $this->countRows('inventory_item_groups');

        $crawler = $client->request('GET', self::ROUTE_NEW_GROUP);
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Create Group')->form([
            'inventory_item_group[name]' => 'Group Facility Scoped',
            'inventory_item_group[colorHex]' => '#FFFFFF',
            'inventory_item_group[scope]' => 'facility',
            'inventory_item_group[facilityCode]' => '',
        ]);

        $client->submit($form);

        self::assertResponseStatusCodeSame(422);
        // FormError on the facilityCode field renders inline next to the
        // input (not bubbled to the top-of-form summary). Match the
        // controller's verbatim message text on the rendered form.
        $body = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('A facility code is required', $body);
        self::assertSame($beforeGroups, $this->countRows('inventory_item_groups'));
    }

    #[Test]
    #[TestDox('Anonymous user gets a redirect or 403 from GET /admin/inventory/groups/new.')]
    public function anonymous_user_is_blocked_from_new(): void
    {
        $client = static::createClient();

        $client->request('GET', self::ROUTE_NEW_GROUP);

        $status = $client->getResponse()->getStatusCode();
        self::assertContains(
            $status,
            [302, 403],
            'Anonymous request to /admin/inventory/groups/new must be denied (3xx redirect to login or 403).',
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
