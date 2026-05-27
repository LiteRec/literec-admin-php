<?php

declare(strict_types=1);

namespace App\Tests\Unit\Inventory\Infrastructure\Acl;

use App\Inventory\Domain\Event\StockReceived;
use App\Inventory\Domain\Event\StockReturned;
use App\Inventory\Domain\Event\StockTransferredIn;
use App\Inventory\Domain\Event\StockTransferredOut;
use App\Inventory\Domain\ValueObject\Comment;
use App\Inventory\Domain\ValueObject\CostPerUnit;
use App\Inventory\Domain\ValueObject\FacilityCode;
use App\Inventory\Domain\ValueObject\InventoryItemId;
use App\Inventory\Domain\ValueObject\PurchaseOrderLineId;
use App\Inventory\Domain\ValueObject\Quantity;
use App\Inventory\Domain\ValueObject\StockBatchId;
use App\Inventory\Domain\ValueObject\StockMovementReason;
use App\Inventory\Domain\ValueObject\TransferLineItem;
use App\Inventory\Infrastructure\Acl\StockReceivedLedgerSubscriber;
use App\Inventory\Infrastructure\Acl\StockReturnedLedgerSubscriber;
use App\Inventory\Infrastructure\Acl\StockTransferredInLedgerSubscriber;
use App\Inventory\Infrastructure\Acl\StockTransferredOutLedgerSubscriber;
use App\Tests\Support\Fake\InMemoryStockMovementLedger;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

/**
 * Pins the per-event subscriber wiring introduced in LRA-94.
 *
 * Each subscriber converts a single domain event into one (or N for
 * transfer events) ledger rows with the right kind and reason. The
 * tests run against the InMemoryStockMovementLedger so the subscriber
 * call shape is validated without booting Doctrine.
 */
#[Small]
final class StockEventLedgerSubscriberTest extends TestCase
{
    private const ITEM = '019571bf-5d51-7000-b500-000000007001';
    private const BATCH = '019571bf-5d51-7000-b500-000000007002';
    private const BATCH_2 = '019571bf-5d51-7000-b500-000000007003';
    private const PO_LINE = '019571bf-5d51-7000-b500-000000007004';

    #[Test]
    #[TestDox('Manual StockReceived (no source PO line) records reason RECEIPT.')]
    public function manual_received_records_receipt_reason(): void
    {
        $ledger = new InMemoryStockMovementLedger();
        $subscriber = new StockReceivedLedgerSubscriber($ledger);

        $subscriber(new StockReceived(
            InventoryItemId::fromString(self::ITEM),
            StockBatchId::fromString(self::BATCH),
            FacilityCode::fromString('MAIN'),
            Quantity::ofUnits(5),
            CostPerUnit::ofCents(199),
            sourceLineId: null,
            comments: Comment::of('manual receive'),
            occurredAt: new DateTimeImmutable('2026-05-26 10:00:00'),
        ));

        $rows = $ledger->rows();
        self::assertCount(1, $rows);
        self::assertSame('RECEIVED', $rows[0]['kind']);
        self::assertSame(StockMovementReason::RECEIPT->value, $rows[0]['reason']);
        self::assertSame('MAIN', $rows[0]['facility_code']);
        self::assertSame(5, $rows[0]['quantity']);
        self::assertSame(199, $rows[0]['cost_per_unit_cents']);
        self::assertSame('manual receive', $rows[0]['operator_note']);
        self::assertNull($rows[0]['transaction_id']);
        self::assertSame(self::BATCH, $rows[0]['stock_batch_id']);
    }

    #[Test]
    #[TestDox('StockReceived with a source PO line records reason PO_RECEIPT.')]
    public function po_received_records_po_receipt_reason(): void
    {
        $ledger = new InMemoryStockMovementLedger();
        $subscriber = new StockReceivedLedgerSubscriber($ledger);

        $subscriber(new StockReceived(
            InventoryItemId::fromString(self::ITEM),
            StockBatchId::fromString(self::BATCH),
            FacilityCode::fromString('MAIN'),
            Quantity::ofUnits(12),
            CostPerUnit::ofCents(250),
            sourceLineId: PurchaseOrderLineId::fromString(self::PO_LINE),
            comments: null,
            occurredAt: new DateTimeImmutable('2026-05-26 10:00:00'),
        ));

        $rows = $ledger->rows();
        self::assertCount(1, $rows);
        self::assertSame(StockMovementReason::PO_RECEIPT->value, $rows[0]['reason']);
        self::assertNull($rows[0]['operator_note']);
    }

    #[Test]
    #[TestDox('StockReturned records a RETURNED row with reason RETURN.')]
    public function returned_records_return_row(): void
    {
        $ledger = new InMemoryStockMovementLedger();
        $subscriber = new StockReturnedLedgerSubscriber($ledger);

        $subscriber(new StockReturned(
            InventoryItemId::fromString(self::ITEM),
            StockBatchId::fromString(self::BATCH),
            FacilityCode::fromString('MAIN'),
            Quantity::ofUnits(3),
            CostPerUnit::ofCents(199),
            new DateTimeImmutable('2026-05-26 10:30:00'),
        ));

        $rows = $ledger->rows();
        self::assertCount(1, $rows);
        self::assertSame('RETURNED', $rows[0]['kind']);
        self::assertSame(StockMovementReason::RETURN->value, $rows[0]['reason']);
        self::assertSame(3, $rows[0]['quantity']);
        self::assertSame(self::BATCH, $rows[0]['stock_batch_id']);
    }

    #[Test]
    #[TestDox('StockTransferredOut writes one TRANSFERRED_OUT row per source batch.')]
    public function transferred_out_writes_one_row_per_line(): void
    {
        $ledger = new InMemoryStockMovementLedger();
        $subscriber = new StockTransferredOutLedgerSubscriber($ledger);

        $subscriber(new StockTransferredOut(
            InventoryItemId::fromString(self::ITEM),
            FacilityCode::fromString('MAIN'),
            FacilityCode::fromString('LAKE'),
            [
                new TransferLineItem(
                    StockBatchId::fromString(self::BATCH),
                    Quantity::ofUnits(2),
                    CostPerUnit::ofCents(100),
                ),
                new TransferLineItem(
                    StockBatchId::fromString(self::BATCH_2),
                    Quantity::ofUnits(3),
                    CostPerUnit::ofCents(150),
                ),
            ],
            new DateTimeImmutable('2026-05-26 11:00:00'),
        ));

        $rows = $ledger->rows();
        self::assertCount(2, $rows);
        self::assertSame('TRANSFERRED_OUT', $rows[0]['kind']);
        self::assertSame(StockMovementReason::TRANSFER_OUT->value, $rows[0]['reason']);
        self::assertSame('MAIN', $rows[0]['facility_code']);
        self::assertSame(2, $rows[0]['quantity']);
        self::assertSame(self::BATCH, $rows[0]['stock_batch_id']);
        self::assertSame('TRANSFERRED_OUT', $rows[1]['kind']);
        self::assertSame(StockMovementReason::TRANSFER_OUT->value, $rows[1]['reason']);
        self::assertSame(3, $rows[1]['quantity']);
        self::assertSame(self::BATCH_2, $rows[1]['stock_batch_id']);
    }

    #[Test]
    #[TestDox('StockTransferredIn writes one TRANSFERRED_IN row per destination batch.')]
    public function transferred_in_writes_one_row_per_line(): void
    {
        $ledger = new InMemoryStockMovementLedger();
        $subscriber = new StockTransferredInLedgerSubscriber($ledger);

        $subscriber(new StockTransferredIn(
            InventoryItemId::fromString(self::ITEM),
            FacilityCode::fromString('MAIN'),
            FacilityCode::fromString('LAKE'),
            [
                new TransferLineItem(
                    StockBatchId::fromString(self::BATCH),
                    Quantity::ofUnits(5),
                    CostPerUnit::ofCents(120),
                ),
            ],
            new DateTimeImmutable('2026-05-26 11:00:00'),
        ));

        $rows = $ledger->rows();
        self::assertCount(1, $rows);
        self::assertSame('TRANSFERRED_IN', $rows[0]['kind']);
        self::assertSame(StockMovementReason::TRANSFER_IN->value, $rows[0]['reason']);
        self::assertSame('LAKE', $rows[0]['facility_code']);
        self::assertSame(5, $rows[0]['quantity']);
    }
}
