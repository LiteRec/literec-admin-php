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
final class PurchaseOrderLifecycleControllerTest extends WebTestCase
{
    use SeedsPurchaseOrderForUi;
    use SignsInUsers;

    private const string TEST_USERNAME = 'po_lifecycle_e2e';
    // NOSONAR — test fixture, not a real credential.
    private const string TEST_PASSWORD = 'CorrectHorseBattery!'; // NOSONAR

    private const string PO_ID = '019571bf-5d51-7000-b500-000000bb0001';
    private const string VENDOR_ID = '019571bf-5d51-7000-b500-000000bb0101';
    private const string LISTING_ID = '019571bf-5d51-7000-b500-000000bb0201';
    private const string ITEM_ID = '019571bf-5d51-7000-b500-000000bb0301';
    private const string LINE_1 = '019571bf-5d51-7000-b500-000000bb0401';
    private const string FACILITY = 'MAIN';
    private const string VENDOR_CODE = 'ACMELC';
    private const string LISTING_CODE = 'LC-ITEM-1';

    private const string ROUTE_DETAIL_PREFIX = '/admin/inventory/purchase-orders/';
    private const string ROUTE_SEND_SUFFIX = '/send';
    private const string ROUTE_VERIFY_SUFFIX = '/verify';
    private const string ROUTE_RECEIVE_FRAGMENT = '/lines/';
    private const string ROUTE_RECEIVE_SUFFIX = '/receive';

    private static function receiveUrl(string $poId, string $lineId): string
    {
        return self::ROUTE_DETAIL_PREFIX . $poId
            . self::ROUTE_RECEIVE_FRAGMENT . $lineId . self::ROUTE_RECEIVE_SUFFIX;
    }

    #[Test]
    #[TestDox('POST /send moves a Draft PO to Sent and the rendered body shows the new status.')]
    public function send_marks_po_sent(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);
        $this->seedSimpleDraft();

        $client->request('POST', self::ROUTE_DETAIL_PREFIX . self::PO_ID . self::ROUTE_SEND_SUFFIX);

        self::assertResponseIsSuccessful();
        $body = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('sent', $body);
        self::assertStringContainsString('po-line-receive-open-' . self::LINE_1, $body);
        self::assertStringNotContainsString('po-send-button', $body);
        self::assertSame('poSent', $client->getResponse()->headers->get('HX-Trigger'));
    }

    #[Test]
    #[TestDox('Receiving the full ordered quantity transitions the PO to FullyReceived and surfaces Verify.')]
    public function receive_full_quantity_enables_verify(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);
        $this->seedSimpleDraft();

        // Send first so we can receive.
        $client->request('POST', self::ROUTE_DETAIL_PREFIX . self::PO_ID . self::ROUTE_SEND_SUFFIX);
        self::assertResponseIsSuccessful();

        $client->request(
            'POST',
            self::receiveUrl(self::PO_ID, self::LINE_1),
            ['receivedQuantityUnits' => 5],
        );

        self::assertResponseIsSuccessful();
        $body = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('fully_received', $body);
        self::assertStringContainsString('po-verify-button', $body);
    }

    #[Test]
    #[TestDox('Receiving partial quantity leaves PO in PartiallyReceived with Receive form still visible.')]
    public function partial_receipt_keeps_status_partially_received(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);
        $this->seedSimpleDraft();

        $client->request('POST', self::ROUTE_DETAIL_PREFIX . self::PO_ID . self::ROUTE_SEND_SUFFIX);
        self::assertResponseIsSuccessful();

        $client->request(
            'POST',
            self::receiveUrl(self::PO_ID, self::LINE_1),
            ['receivedQuantityUnits' => 2],
        );

        self::assertResponseIsSuccessful();
        $body = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('partially_received', $body);
        self::assertStringContainsString('po-line-receive-open-' . self::LINE_1, $body);
    }

    #[Test]
    #[TestDox('Verify on a FullyReceived PO transitions it to Verified and removes all action buttons.')]
    public function verify_finalises_lifecycle(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);
        $this->seedSimpleDraft();

        $client->request('POST', self::ROUTE_DETAIL_PREFIX . self::PO_ID . self::ROUTE_SEND_SUFFIX);
        $client->request(
            'POST',
            self::receiveUrl(self::PO_ID, self::LINE_1),
            ['receivedQuantityUnits' => 5],
        );

        $client->request('POST', self::ROUTE_DETAIL_PREFIX . self::PO_ID . self::ROUTE_VERIFY_SUFFIX);

        self::assertResponseIsSuccessful();
        $body = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('verified', $body);
        self::assertStringContainsString('po-no-actions', $body);
        self::assertStringNotContainsString('po-send-button', $body);
        self::assertStringNotContainsString('po-verify-button', $body);
    }

    #[Test]
    #[TestDox('Send on an already-Sent PO returns 409 (stale-transition defence-in-depth).')]
    public function stale_send_returns_409(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);
        $this->seedSimpleDraft();

        // First send succeeds.
        $client->request('POST', self::ROUTE_DETAIL_PREFIX . self::PO_ID . self::ROUTE_SEND_SUFFIX);
        self::assertResponseIsSuccessful();

        // Second send must be rejected by the allowedTransitions gate.
        $client->request('POST', self::ROUTE_DETAIL_PREFIX . self::PO_ID . self::ROUTE_SEND_SUFFIX);
        self::assertResponseStatusCodeSame(409);
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
