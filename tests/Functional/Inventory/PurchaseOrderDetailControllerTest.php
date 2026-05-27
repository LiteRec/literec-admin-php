<?php

declare(strict_types=1);

namespace App\Tests\Functional\Inventory;

use App\Tests\Support\Trait\SeedsPurchaseOrderForUi;
use App\Tests\Support\Trait\SignsInUsers;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[Large]
#[Group('database')]
final class PurchaseOrderDetailControllerTest extends WebTestCase
{
    use SeedsPurchaseOrderForUi;
    use SignsInUsers;

    private const string TEST_USERNAME = 'po_detail_e2e';
    // NOSONAR — test fixture, not a real credential.
    private const string TEST_PASSWORD = 'CorrectHorseBattery!'; // NOSONAR

    private const string PO_ID = '019571bf-5d51-7000-b500-000000aa0001';
    private const string VENDOR_ID = '019571bf-5d51-7000-b500-000000aa0101';
    private const string LISTING_ID = '019571bf-5d51-7000-b500-000000aa0201';
    private const string ITEM_ID = '019571bf-5d51-7000-b500-000000aa0301';
    private const string LINE_1 = '019571bf-5d51-7000-b500-000000aa0401';
    private const string FACILITY = 'MAIN';
    private const string VENDOR_CODE = 'ACMEPO';
    private const string LISTING_CODE = 'PO-ITEM-1';

    private const string ROUTE_DETAIL_PREFIX = '/admin/inventory/purchase-orders/';
    private const string ROUTE_NEW = '/admin/inventory/purchase-orders/new';

    #[Test]
    #[TestDox('Anonymous request to a detail URL is redirected to login.')]
    public function anonymous_denied(): void
    {
        $client = static::createClient();

        $client->request('GET', self::ROUTE_DETAIL_PREFIX . self::PO_ID);

        self::assertResponseRedirects();
    }

    #[Test]
    #[TestDox('Detail page for a Draft PO shows the line and the "Mark sent" button.')]
    public function draft_shows_send_button(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);
        $this->seedSimpleDraft();

        $client->request('GET', self::ROUTE_DETAIL_PREFIX . self::PO_ID);

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-testid="po-detail"]');
        self::assertSelectorTextContains('[data-testid="po-status"]', 'draft');
        self::assertSelectorExists('[data-testid="po-line-row-' . self::LINE_1 . '"]');
        self::assertSelectorExists('[data-testid="po-send-button"]');
        // No receive button when status is Draft.
        $body = (string) $client->getResponse()->getContent();
        self::assertStringNotContainsString('po-line-receive-open-' . self::LINE_1, $body);
    }

    #[Test]
    #[TestDox('GET /new renders the create form with one initial line row.')]
    public function new_form_renders(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);

        $client->request('GET', self::ROUTE_NEW);

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form#purchase-order-form');
        self::assertSelectorExists('input[data-testid="po-vendor-id"]');
        self::assertSelectorExists('input[data-testid="po-facility-code"]');
        self::assertSelectorExists('button[data-testid="po-create-submit"]');
    }

    #[Test]
    #[TestDox('Unknown PO id returns 404.')]
    public function unknown_po_returns_404(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);

        // Valid UUID v7 shape but never seeded.
        $client->request('GET', self::ROUTE_DETAIL_PREFIX . '019571bf-5d51-7000-b500-000000ffffff');

        self::assertResponseStatusCodeSame(404);
    }

    private function seedSimpleDraft(): void
    {
        $this->seedDraftPurchaseOrder(
            poId: self::PO_ID,
            vendorId: self::VENDOR_ID,
            vendorCode: self::VENDOR_CODE,
            listingId: self::LISTING_ID,
            listingCode: self::LISTING_CODE,
            itemId: self::ITEM_ID,
            facilityCode: self::FACILITY,
            lineSpecs: [
                ['lineId' => self::LINE_1, 'orderedUnits' => 5, 'costPerUnitCents' => 250],
            ],
        );
    }
}
