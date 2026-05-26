<?php

declare(strict_types=1);

namespace App\Inventory\Application\Query\View;

use DateTimeImmutable;

/**
 * Read-model projection of a single {@see App\Inventory\Domain\StockBatch}.
 *
 * Final readonly, primitive scalars + DateTimeImmutable only — no
 * aggregate or value object ever crosses the read boundary.
 */
final readonly class StockBatchView
{
    public function __construct(
        public string $stockBatchId,
        public int $originalQuantityUnits,
        public int $remainingQuantityUnits,
        public int $costPerUnitCents,
        public ?string $sourceLineId,
        public ?string $comment,
        public DateTimeImmutable $receivedAt,
    ) {
    }
}
