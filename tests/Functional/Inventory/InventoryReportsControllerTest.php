<?php

declare(strict_types=1);

namespace App\Tests\Functional\Inventory;

use App\Tests\Support\Trait\SeedsInventoryItemForUi;
use App\Tests\Support\Trait\SignsInUsers;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * End-to-end coverage for the LRA-91 Inventory Reports dashboard,
 * partial card endpoints, CSV exports, and barcode batch print.
 *
 * DAMA wraps each test in a transaction rolled back at teardown so
 * seeded rows stay isolated between tests.
 */
#[Large]
#[Group('database')]
final class InventoryReportsControllerTest extends WebTestCase
{
    use SignsInUsers;
    use SeedsInventoryItemForUi;

    private const string TEST_USERNAME = 'reports_e2e';

    private const string ITEM_ID = '019571bf-5d51-7000-b500-0000000091a1';
    private const string ITEM_ID_LOW = '019571bf-5d51-7000-b500-0000000091a2';
    private const string LISTING_ID = '019571bf-5d51-7000-b500-0000000091b1';
    private const string LISTING_ID_LOW = '019571bf-5d51-7000-b500-0000000091b2';
    private const string BATCH_ID = '019571bf-5d51-7000-b500-0000000091c1';
    private const string BATCH_ID_LOW = '019571bf-5d51-7000-b500-0000000091c2';
    private const string FACILITY = 'MAIN';
    private const string LISTING_CODE = 'RPT-WID-1';
    private const string LISTING_CODE_LOW = 'RPT-WID-2';
    private const string LISTING_NAME = 'Report Widget';
    private const string LISTING_NAME_LOW = 'Low Stock Widget';

    private const string ROUTE_INDEX = '/admin/inventory/reports';
    private const string ROUTE_CURRENT_STOCK_CARD = '/admin/inventory/reports/current-stock/_card';
    private const string ROUTE_ENTRY_LOG_CARD = '/admin/inventory/reports/entry-log/_card';
    private const string ROUTE_LOW_STOCK_CARD = '/admin/inventory/reports/low-stock/_card';
    private const string ROUTE_CURRENT_STOCK_EXPORT = '/admin/inventory/reports/current-stock/export.csv';
    private const string ROUTE_ENTRY_LOG_EXPORT = '/admin/inventory/reports/entry-log/export.csv';
    private const string ROUTE_LOW_STOCK_EXPORT = '/admin/inventory/reports/low-stock/export.csv';
    private const string ROUTE_BARCODE_FORM = '/admin/inventory/reports/barcodes';
    private const string ROUTE_BARCODE_PRINT = '/admin/inventory/reports/barcodes/print';

    #[Test]
    #[TestDox('GET /admin/inventory/reports renders four live report cards plus two "Coming soon" placeholders.')]
    public function get_index_returns_dashboard_with_six_tiles(): void
    {
        $client = $this->signInAndSeed();

        $client->request('GET', self::ROUTE_INDEX);

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('section[data-testid="report-card-current-stock"]');
        self::assertSelectorExists('section[data-testid="report-card-entry-log"]');
        self::assertSelectorExists('section[data-testid="report-card-low-stock"]');
        self::assertSelectorExists('section[data-testid="report-card-barcode-batch"]');
        self::assertSelectorExists('section[data-testid="report-card-placeholder-sales-breakdown"]');
        self::assertSelectorExists('section[data-testid="report-card-placeholder-demographic-breakdown"]');
    }

    #[Test]
    #[TestDox('GET current-stock card partial returns 200 with the seeded item row.')]
    public function get_current_stock_card_returns_seeded_row(): void
    {
        $client = $this->signInAndSeed();

        $client->request('GET', self::ROUTE_CURRENT_STOCK_CARD);

        self::assertResponseIsSuccessful();
        self::assertSelectorExists(sprintf(
            'tr[data-testid="report-current-stock-row-%s"]',
            self::ITEM_ID,
        ));
    }

    #[Test]
    #[TestDox('GET current-stock CSV export returns text/csv response with the seeded item code in the body.')]
    public function get_current_stock_export_returns_csv(): void
    {
        $client = $this->signInAndSeed();

        $client->request('GET', self::ROUTE_CURRENT_STOCK_EXPORT);

        self::assertResponseIsSuccessful();
        $contentType = (string) $client->getResponse()->headers->get('Content-Type');
        self::assertStringStartsWith('text/csv', $contentType);
        $disposition = (string) $client->getResponse()->headers->get('Content-Disposition');
        self::assertStringStartsWith('attachment', $disposition);
        self::assertStringContainsString('filename="current-stock-', $disposition);

        $body = self::captureStreamedBody($client);
        self::assertStringContainsString('Code,Name,Kind,Facility,OnHand,ReorderThreshold,AtOrBelowThreshold', $body);
        self::assertStringContainsString(self::LISTING_CODE, $body);
    }

    #[Test]
    #[TestDox('GET entry-log CSV export returns text/csv with the seeded receipt row.')]
    public function get_entry_log_export_returns_csv(): void
    {
        $client = $this->signInAndSeed();
        $this->seedReceivedMovement(self::ITEM_ID, self::BATCH_ID);

        $client->request('GET', self::ROUTE_ENTRY_LOG_EXPORT);

        self::assertResponseIsSuccessful();
        $disposition = (string) $client->getResponse()->headers->get('Content-Disposition');
        self::assertStringContainsString('filename="entry-log-', $disposition);

        $body = self::captureStreamedBody($client);
        self::assertStringContainsString(self::LISTING_CODE, $body);
        self::assertStringContainsString('RECEIVED', $body);
    }

    #[Test]
    #[TestDox('GET low-stock CSV export returns text/csv with the threshold alert row.')]
    public function get_low_stock_export_returns_csv(): void
    {
        $client = $this->signInAndSeed();
        $this->seedItemBelowThreshold();

        $client->request('GET', self::ROUTE_LOW_STOCK_EXPORT);

        self::assertResponseIsSuccessful();
        $disposition = (string) $client->getResponse()->headers->get('Content-Disposition');
        self::assertStringContainsString('filename="low-stock-', $disposition);

        $body = self::captureStreamedBody($client);
        self::assertStringContainsString(self::ITEM_ID_LOW, $body);
    }

    #[Test]
    #[TestDox('GET low-stock card returns the seeded threshold-tripped item.')]
    public function get_low_stock_card_returns_threshold_alert(): void
    {
        $client = $this->signInAndSeed();
        $this->seedItemBelowThreshold();

        $client->request('GET', self::ROUTE_LOW_STOCK_CARD);

        self::assertResponseIsSuccessful();
        self::assertSelectorExists(sprintf(
            'tr[data-testid="report-low-stock-row-%s"]',
            self::ITEM_ID_LOW,
        ));
    }

    #[Test]
    #[TestDox('GET entry-log card with kind=RECEIVED returns the seeded receipt ledger row.')]
    public function get_entry_log_card_filters_by_received_kind(): void
    {
        $client = $this->signInAndSeed();
        $movementId = $this->seedReceivedMovement(self::ITEM_ID, self::BATCH_ID);

        $client->request('GET', self::ROUTE_ENTRY_LOG_CARD . '?kind=RECEIVED');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists(sprintf(
            'tr[data-testid="report-entry-log-row-%s"]',
            $movementId,
        ));
    }

    #[Test]
    #[TestDox('GET /barcodes returns the picker form with the seeded item checkbox.')]
    public function get_barcode_form_returns_picker(): void
    {
        $client = $this->signInAndSeed();

        $client->request('GET', self::ROUTE_BARCODE_FORM);

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('table[data-testid="report-barcode-picker-table"]');
        self::assertSelectorExists(sprintf(
            'input[data-testid="report-barcode-pick-%s"]',
            self::ITEM_ID,
        ));
    }

    #[Test]
    #[TestDox('The barcode picker filter inputs expose programmatic labels (WCAG 3.3.2).')]
    public function barcode_filter_inputs_have_programmatic_labels(): void
    {
        $client = $this->signInAndSeed();

        $crawler = $client->request('GET', self::ROUTE_BARCODE_FORM);

        self::assertResponseIsSuccessful();
        $searchLabel = $crawler->filter('input[name="search"]')->closest('label');
        $searchText = $searchLabel !== null
            ? trim($searchLabel->filter('.sr-only')->text(''))
            : null;
        self::assertSame('Search by name or code', $searchText, 'Search input needs a wrapping label.');

        $facilityLabel = $crawler->filter('input[name="facilityCode"]')->closest('label');
        $facilityText = $facilityLabel !== null
            ? trim($facilityLabel->filter('.sr-only')->text(''))
            : null;
        self::assertSame('Facility code', $facilityText, 'Facility-code input needs a wrapping label.');
    }

    #[Test]
    #[TestDox('POST /barcodes/print with two item ids returns 200 and renders a label per id.')]
    public function post_barcode_print_renders_label_per_item(): void
    {
        $client = $this->signInAndSeed();
        $this->seedItemBelowThreshold();

        $client->request('POST', self::ROUTE_BARCODE_PRINT, [
            'itemIds' => [self::ITEM_ID, self::ITEM_ID_LOW],
        ]);

        self::assertResponseIsSuccessful();
        $body = (string) $client->getResponse()->getContent();
        self::assertStringContainsString(
            sprintf('data-testid="report-barcode-label-%s"', self::ITEM_ID),
            $body,
        );
        self::assertStringContainsString(
            sprintf('data-testid="report-barcode-label-%s"', self::ITEM_ID_LOW),
            $body,
        );
    }

    #[Test]
    #[TestDox('Anonymous request to the reports index is denied (302 redirect or 403).')]
    public function anonymous_is_blocked_from_index(): void
    {
        $client = static::createClient();

        $client->request('GET', self::ROUTE_INDEX);

        $status = $client->getResponse()->getStatusCode();
        self::assertContains(
            $status,
            [302, 403],
            'Anonymous request must be denied (3xx redirect or 403).',
        );
    }

    private function signInAndSeed(): KernelBrowser
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);

        $this->seedInventoryItemWithStock(
            itemId: self::ITEM_ID,
            listingId: self::LISTING_ID,
            batchId: self::BATCH_ID,
            listingCode: self::LISTING_CODE,
            listingName: self::LISTING_NAME,
            facilityCode: self::FACILITY,
        );

        return $client;
    }

    /**
     * Seeds a second item that should trip the low-stock alert: 1 on
     * hand at MAIN with a reorder threshold of 5 (shortfall = 4).
     */
    private function seedItemBelowThreshold(): void
    {
        $this->seedInventoryItemWithStock(
            itemId: self::ITEM_ID_LOW,
            listingId: self::LISTING_ID_LOW,
            batchId: self::BATCH_ID_LOW,
            listingCode: self::LISTING_CODE_LOW,
            listingName: self::LISTING_NAME_LOW,
            facilityCode: self::FACILITY,
            initialQuantityUnits: 1,
        );

        // Bump the threshold above the on-hand qty so the item appears
        // in low-stock results. The seed helper hard-codes threshold=0,
        // so we update the row directly.
        $conn = static::getContainer()->get(Connection::class);
        self::assertInstanceOf(Connection::class, $conn);
        $conn->update(
            'inventory_items',
            ['reorder_threshold' => 5],
            ['id' => self::ITEM_ID_LOW],
        );
    }

    /**
     * Inserts one RECEIVED ledger row directly via DBAL for the given
     * item + batch and returns its generated UUID so tests can assert
     * the projection picks it up.
     */
    private function seedReceivedMovement(string $itemId, string $batchId): string
    {
        $conn = static::getContainer()->get(Connection::class);
        self::assertInstanceOf(Connection::class, $conn);

        $movementId = Uuid::v7()->toRfc4122();
        $conn->insert('inventory_stock_movements', [
            'id' => $movementId,
            'item_id' => $itemId,
            'facility_code' => self::FACILITY,
            'stock_batch_id' => $batchId,
            'kind' => 'RECEIVED',
            'reason' => 'receipt',
            'quantity' => 5,
            'cost_per_unit_cents' => 100,
            'operator_note' => 'initial receipt',
            'transaction_id' => null,
            'listing_id' => null,
            'recorded_at' => (new DateTimeImmutable('2026-05-27 12:00:00', new DateTimeZone('UTC')))
                ->format('Y-m-d H:i:s'),
        ]);

        return $movementId;
    }

    private static function captureStreamedBody(KernelBrowser $client): string
    {
        $response = $client->getResponse();
        ob_start();
        $response->sendContent();
        $body = (string) ob_get_clean();
        if ($body === '') {
            $body = (string) $client->getInternalResponse()->getContent();
        }
        return $body;
    }
}
