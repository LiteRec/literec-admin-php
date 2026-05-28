<?php

declare(strict_types=1);

namespace App\Tests\Functional\Inventory;

use App\Tests\Support\Trait\SignsInUsers;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Drives the LRA-90 Purchase Orders list page through the real
 * container. Seeding a row would require a full Vendor + Listing +
 * InventoryItem + PO chain; for the list-page test we are content to
 * exercise the empty-state path + filter rendering. Row rendering is
 * covered by the detail/lifecycle tests which do the full seed.
 */
#[Large]
#[Group('database')]
final class PurchaseOrderListControllerTest extends WebTestCase
{
    use SignsInUsers;

    private const string TEST_USERNAME = 'po_list_e2e';

    private const string ROUTE_INDEX = '/admin/inventory/purchase-orders';
    private const string ROUTE_TABLE = '/admin/inventory/purchase-orders/_table';

    #[Test]
    #[TestDox('Anonymous request to the index route is denied (redirect to login).')]
    public function anonymous_denied(): void
    {
        $client = static::createClient();

        $client->request('GET', self::ROUTE_INDEX);

        // The security firewall redirects anonymous traffic to /login.
        self::assertResponseRedirects();
    }

    #[Test]
    #[TestDox('Authenticated user sees the page shell and the empty state when no POs exist.')]
    public function shows_empty_state_for_authenticated_user(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);

        $client->request('GET', self::ROUTE_INDEX);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main h1', 'Purchase Orders');
        self::assertSelectorTextContains('main', 'No purchase orders match your filters.');
        self::assertSelectorExists('[data-testid="open-new-purchase-order"]');
    }

    #[Test]
    #[TestDox('Authenticated user can request the table partial directly and get HTML without the shell.')]
    public function table_partial_returns_html_without_shell(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);

        $client->request('GET', self::ROUTE_TABLE);

        self::assertResponseIsSuccessful();
        $body = (string) $client->getResponse()->getContent();
        self::assertSame(
            0,
            preg_match('/<!doctype\b|<html\b/i', $body),
            'Partial response must not include the full HTML shell.',
        );
    }

    #[Test]
    #[TestDox('Filter form is rendered with the vendor, facility, and status controls.')]
    public function filters_render(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);

        $client->request('GET', self::ROUTE_INDEX);

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('input[name="vendorId"]');
        self::assertSelectorExists('input[name="facilityCode"]');
        self::assertSelectorExists('select[name="status"]');
    }
}
