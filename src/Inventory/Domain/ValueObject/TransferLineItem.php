<?php

declare(strict_types=1);

namespace App\Inventory\Domain\ValueObject;

/**
 * One slice of a stock transfer: the batch identity, the units moved
 * from it, and the cost-per-unit that travels with those units.
 *
 * Used as the payload entry of {@see App\Inventory\Domain\Event\StockTransferredOut}
 * and {@see App\Inventory\Domain\Event\StockTransferredIn}; on the
 * source side {@see $stockBatchId} names the source batch consumed, on
 * the destination side it names the freshly-created destination batch.
 */
final readonly class TransferLineItem
{
    public function __construct(
        public StockBatchId $stockBatchId,
        public Quantity $quantity,
        public CostPerUnit $costPerUnit,
    ) {
    }
}
