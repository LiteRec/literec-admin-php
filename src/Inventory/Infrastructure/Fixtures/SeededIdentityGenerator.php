<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Fixtures;

use App\Inventory\Domain\IdentityGenerator;
use App\Inventory\Domain\ValueObject\ComboId;
use App\Inventory\Domain\ValueObject\InventoryItemId;
use App\Inventory\Domain\ValueObject\ItemGroupId;
use App\Inventory\Domain\ValueObject\ItemLinkId;
use App\Inventory\Domain\ValueObject\PurchaseOrderId;
use App\Inventory\Domain\ValueObject\PurchaseOrderLineId;
use App\Inventory\Domain\ValueObject\StockBatchId;
use App\Inventory\Domain\ValueObject\StockMovementId;
use App\Inventory\Domain\ValueObject\VendorId;
use App\Shared\Infrastructure\Fixtures\DeterministicUuid7Sequencer;
use Psr\Clock\ClockInterface;

/**
 * Deterministic {@see IdentityGenerator} implementation used by
 * fixture loads (LRA-92).
 *
 * Combines the FIXTURE_SEED with a monotonic counter to yield UUID v7
 * strings that are stable across repeated loads at the same seed
 * (asserted by the LRA-92 determinism test). Production uses
 * {@see \App\Inventory\Infrastructure\Identity\Uuid7IdentityGenerator}
 * which derives UUIDs from the real wall clock — this adapter is wired
 * only in the dev fixtures container override and in tests that
 * explicitly need deterministic identities.
 */
final class SeededIdentityGenerator implements IdentityGenerator
{
    private readonly DeterministicUuid7Sequencer $sequencer;

    public function __construct(ClockInterface $clock, int $seed)
    {
        $this->sequencer = new DeterministicUuid7Sequencer($clock, $seed);
    }

    public function nextInventoryItemId(): InventoryItemId
    {
        return InventoryItemId::fromString($this->sequencer->next());
    }

    public function nextStockBatchId(): StockBatchId
    {
        return StockBatchId::fromString($this->sequencer->next());
    }

    public function nextStockMovementId(): StockMovementId
    {
        return StockMovementId::fromString($this->sequencer->next());
    }

    public function nextVendorId(): VendorId
    {
        return VendorId::fromString($this->sequencer->next());
    }

    public function nextPurchaseOrderId(): PurchaseOrderId
    {
        return PurchaseOrderId::fromString($this->sequencer->next());
    }

    public function nextPurchaseOrderLineId(): PurchaseOrderLineId
    {
        return PurchaseOrderLineId::fromString($this->sequencer->next());
    }

    public function nextComboId(): ComboId
    {
        return ComboId::fromString($this->sequencer->next());
    }

    public function nextItemGroupId(): ItemGroupId
    {
        return ItemGroupId::fromString($this->sequencer->next());
    }

    public function nextItemLinkId(): ItemLinkId
    {
        return ItemLinkId::fromString($this->sequencer->next());
    }
}
