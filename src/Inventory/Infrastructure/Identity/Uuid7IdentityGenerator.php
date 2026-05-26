<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Identity;

use App\Inventory\Domain\IdentityGenerator;
use App\Inventory\Domain\ValueObject\ComboId;
use App\Inventory\Domain\ValueObject\InventoryItemId;
use App\Inventory\Domain\ValueObject\ItemGroupId;
use App\Inventory\Domain\ValueObject\PurchaseOrderId;
use App\Inventory\Domain\ValueObject\PurchaseOrderLineId;
use App\Inventory\Domain\ValueObject\StockBatchId;
use App\Inventory\Domain\ValueObject\StockMovementId;
use App\Inventory\Domain\ValueObject\VendorId;
use Psr\Clock\ClockInterface;
use Symfony\Component\Uid\UuidV7;

final class Uuid7IdentityGenerator implements IdentityGenerator
{
    public function __construct(private readonly ClockInterface $clock)
    {
    }

    public function nextInventoryItemId(): InventoryItemId
    {
        return InventoryItemId::fromString(UuidV7::generate($this->clock->now()));
    }

    public function nextStockBatchId(): StockBatchId
    {
        return StockBatchId::fromString(UuidV7::generate($this->clock->now()));
    }

    public function nextStockMovementId(): StockMovementId
    {
        return StockMovementId::fromString(UuidV7::generate($this->clock->now()));
    }

    public function nextVendorId(): VendorId
    {
        return VendorId::fromString(UuidV7::generate($this->clock->now()));
    }

    public function nextPurchaseOrderId(): PurchaseOrderId
    {
        return PurchaseOrderId::fromString(UuidV7::generate($this->clock->now()));
    }

    public function nextPurchaseOrderLineId(): PurchaseOrderLineId
    {
        return PurchaseOrderLineId::fromString(UuidV7::generate($this->clock->now()));
    }

    public function nextComboId(): ComboId
    {
        return ComboId::fromString(UuidV7::generate($this->clock->now()));
    }

    public function nextItemGroupId(): ItemGroupId
    {
        return ItemGroupId::fromString(UuidV7::generate($this->clock->now()));
    }
}
