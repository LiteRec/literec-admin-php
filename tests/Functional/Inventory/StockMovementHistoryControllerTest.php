<?php

declare(strict_types=1);

namespace App\Tests\Functional\Inventory;

use App\Tests\Support\Trait\SeedsInventoryItemForUi;
use App\Tests\Support\Trait\SignsInUsers;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * End-to-end coverage for the LRA-88 history page, FIFO batch panel,
 * and CSV export. DAMA wraps each test in a transaction rolled back
 * at teardown so seeded rows stay isolated.
 */
#[Large]
#[Group('database')]
final class StockMovementHistoryControllerTest extends WebTestCase
{
    use SignsInUsers;
    use SeedsInventoryItemForUi;

    private const string TEST_USERNAME = 'history_e2e';
    // NOSONAR — test fixture, not a real credential.
    private const string TEST_PASSWORD = 'CorrectHorseBattery!'; // NOSONAR

    private const string ITEM_ID = '019571bf-5d51-7000-b500-00000000ff01';
    private const string LISTING_ID = '019571bf-5d51-7000-b500-00000000ff02';
    private const string BATCH_ID = '019571bf-5d51-7000-b500-00000000ff03';
    private const string FACILITY = 'MAIN';
    private const string LISTING_CODE = 'HIS-WID-1';
    private const string LISTING_NAME = 'History Widget';

    private const string ROUTE_INDEX = '/admin/inventory/%s/history';
    private const string ROUTE_MOVEMENTS = '/admin/inventory/%s/history/_movements';
    private const string ROUTE_BATCHES = '/admin/inventory/%s/history/_batches';
    private const string ROUTE_EXPORT = '/admin/inventory/%s/history.csv';
    private const string HISTORY_COUNT_SELECTOR = 'span#history-count';
    private const string SINGLE_MOVEMENT_COUNT_TEXT = 'Showing 1 of 1 movements';

    #[Test]
    #[TestDox('GET /admin/inventory/{itemId}/history returns 200 with one movement row + matching count.')]
    public function get_index_returns_seeded_row_and_count(): void
    {
        $client = $this->signInAndSeed();

        $client->request('GET', sprintf(self::ROUTE_INDEX, self::ITEM_ID));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main h1', 'Stock Movement History');
        self::assertSelectorExists('table[data-testid="history-table"]');
        self::assertSelectorTextContains(self::HISTORY_COUNT_SELECTOR, self::SINGLE_MOVEMENT_COUNT_TEXT);
    }

    #[Test]
    #[TestDox('Filtering by kind=CONSUMED returns only consume rows.')]
    public function filter_by_consumed_kind_returns_only_consume_rows(): void
    {
        $client = $this->signInAndSeed();
        $this->seedConsumedMovement();

        $client->request('GET', sprintf(self::ROUTE_MOVEMENTS, self::ITEM_ID) . '?kind=CONSUMED');

        self::assertResponseIsSuccessful();
        // The CONSUMED row counts as 1; the RECEIVED row from seed is excluded.
        self::assertSelectorTextContains(self::HISTORY_COUNT_SELECTOR, self::SINGLE_MOVEMENT_COUNT_TEXT);
        // Reinforce: assert actual table contents reflect the filter.
        // Kinds render as human labels via StockMovementKind::label(),
        // so the visible text is "Consumed" / "Received", not the raw
        // enum value.
        $tbodyText = (string) $client->getCrawler()
            ->filter('table[data-testid="history-table"] tbody')->text();
        self::assertStringContainsString('Consumed', $tbodyText);
        self::assertStringNotContainsString('Received', $tbodyText);
    }

    #[Test]
    #[TestDox('Filtering by an unknown kind value is silently ignored (falls back to no kind filter).')]
    public function unknown_kind_filter_is_silently_ignored(): void
    {
        $client = $this->signInAndSeed();

        $client->request('GET', sprintf(self::ROUTE_MOVEMENTS, self::ITEM_ID) . '?kind=BOGUS');

        self::assertResponseIsSuccessful();
        // Still shows the one seeded RECEIVED row.
        self::assertSelectorTextContains(self::HISTORY_COUNT_SELECTOR, self::SINGLE_MOVEMENT_COUNT_TEXT);
    }

    #[Test]
    #[TestDox('GET /admin/inventory/{itemId}/history/_batches returns 200 with the FIFO facility heading.')]
    public function get_batches_partial_returns_facility_heading(): void
    {
        $client = $this->signInAndSeed();

        $client->request('GET', sprintf(self::ROUTE_BATCHES, self::ITEM_ID));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h2#batch-panel-heading', 'FIFO batches');
        self::assertSelectorTextContains(
            sprintf('h3[data-testid="history-batch-facility-%s"]', self::FACILITY),
            'Facility ' . self::FACILITY,
        );
        // The single seeded batch is the head of FIFO → carries the badge.
        self::assertSelectorExists('span[data-testid="history-batch-next-to-consume"]');
    }

    #[Test]
    #[TestDox('CSV export returns 200, text/csv content type, attachment disposition, and a header + data row.')]
    public function csv_export_returns_csv_response(): void
    {
        $client = $this->signInAndSeed();

        $client->request('GET', sprintf(self::ROUTE_EXPORT, self::ITEM_ID));

        self::assertResponseStatusCodeSame(200);
        $contentType = (string) $client->getResponse()->headers->get('Content-Type');
        self::assertStringStartsWith('text/csv', $contentType);
        $disposition = (string) $client->getResponse()->headers->get('Content-Disposition');
        self::assertStringStartsWith('attachment', $disposition);
        // history-<itemId8>-<timestamp>.csv naming contract from the spec.
        self::assertStringContainsString('filename="history-019571bf-', $disposition);
        self::assertStringContainsString('.csv"', $disposition);

        // StreamedResponse: the callback writes to php://output during
        // sendContent(); capture it with output buffering. Re-call is
        // safe because StreamedResponse guards the inner callback with
        // a sent flag — see Symfony\Component\HttpFoundation\StreamedResponse.
        $response = $client->getResponse();
        ob_start();
        $response->sendContent();
        $body = (string) ob_get_clean();

        // Some test client setups intercept the stream and return an
        // empty string for sendContent(); fall back to reading the
        // captured InternalResponse body if needed.
        if ($body === '') {
            $body = (string) $client->getInternalResponse()->getContent();
        }

        $expectedHeader = 'Date,Kind,Reason,Facility,Quantity,'
            . 'BatchReceived,CostPerUnit,OperatorNote,TransactionId';
        self::assertStringContainsString($expectedHeader, $body);
        self::assertStringContainsString('RECEIVED', $body);
        self::assertStringContainsString(self::FACILITY, $body);
    }

    #[Test]
    #[TestDox('Anonymous user is blocked from the history index route.')]
    public function anonymous_is_blocked(): void
    {
        $client = static::createClient();

        $client->request('GET', sprintf(self::ROUTE_INDEX, self::ITEM_ID));

        $status = $client->getResponse()->getStatusCode();
        self::assertContains(
            $status,
            [302, 403],
            'Anonymous request must be denied (3xx redirect or 403).',
        );
    }

    /**
     * Signs a user in, seeds the inventory item with one batch, and
     * inserts a baseline RECEIVED ledger row so the movements table
     * has something to render.
     */
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

        $this->seedReceivedMovement();

        return $client;
    }

    private function seedReceivedMovement(): void
    {
        $this->insertMovementRow(
            kind: 'RECEIVED',
            reason: 'receipt',
            quantity: 5,
            stockBatchId: self::BATCH_ID,
            recordedAt: '2026-05-27 12:00:00',
            operatorNote: 'initial receipt',
            transactionId: null,
            listingId: null,
        );
    }

    private function seedConsumedMovement(): void
    {
        $this->insertMovementRow(
            kind: 'CONSUMED',
            reason: 'sale',
            quantity: 1,
            stockBatchId: self::BATCH_ID,
            recordedAt: '2026-05-27 13:00:00',
            operatorNote: null,
            transactionId: '019571bf-5d51-7000-b500-00000000aa01',
            listingId: self::LISTING_ID,
        );
    }

    /**
     * Writes one ledger row directly via DBAL. The test env binds the
     * StockMovementLedger port to an in-memory fake (config/services.yaml
     * `when@test`), so the production-shape Doctrine writer is not on
     * the autowire path; for this read-side test we need the row in
     * Postgres so the projection picks it up.
     */
    private function insertMovementRow(
        string $kind,
        string $reason,
        int $quantity,
        ?string $stockBatchId,
        string $recordedAt,
        ?string $operatorNote,
        ?string $transactionId,
        ?string $listingId,
    ): void {
        $conn = static::getContainer()->get(Connection::class);
        self::assertInstanceOf(Connection::class, $conn);

        $conn->insert('inventory_stock_movements', [
            'id' => Uuid::v7()->toRfc4122(),
            'item_id' => self::ITEM_ID,
            'facility_code' => self::FACILITY,
            'stock_batch_id' => $stockBatchId,
            'kind' => $kind,
            'reason' => $reason,
            'quantity' => $quantity,
            'cost_per_unit_cents' => 100,
            'operator_note' => $operatorNote,
            'transaction_id' => $transactionId,
            'listing_id' => $listingId,
            'recorded_at' => (new DateTimeImmutable($recordedAt, new DateTimeZone('UTC')))
                ->format('Y-m-d H:i:s'),
        ]);
    }
}
