<?php

declare(strict_types=1);

namespace App\Inventory\Application\Command;

use App\Inventory\Domain\Exception\AdjustmentReasonRequired;
use App\Inventory\Domain\Exception\InvalidStockAdjustmentSubReason;
use App\Inventory\Domain\IdentityGenerator;
use App\Inventory\Domain\InventoryItems;
use App\Inventory\Domain\ValueObject\Comment;
use App\Inventory\Domain\ValueObject\CostPerUnit;
use App\Inventory\Domain\ValueObject\FacilityCode;
use App\Inventory\Domain\ValueObject\InventoryItemId;
use App\Inventory\Domain\ValueObject\Quantity;
use App\Inventory\Domain\StockMovementLedger;
use App\Inventory\Domain\ValueObject\StockAdjustmentDirection;
use App\Inventory\Domain\ValueObject\StockAdjustmentReason;
use App\Inventory\Domain\ValueObject\StockMovementReason;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;

/**
 * Take-Inventory bulk-count handler.
 *
 * Computes the signed delta between the recorded on-hand quantity and
 * the operator's counted target. A positive delta books a zero-cost
 * found-stock batch (paper adjustment — no acquisition cost) tagged
 * with the operator's reason. A negative delta consumes from FIFO
 * batches with reason {@see StockMovementReason::ADJUSTMENT}. A zero
 * delta is a no-op.
 *
 * The operator reason is non-empty (guarded here) and rides along on
 * the synthetic batch's {@see Comment}. The optional
 * {@see StockAdjustmentReason} sub-category (LRA-94) is captured on
 * the inventory_stock_movements ledger row this handler writes inline
 * — the StockMovementRecorded domain event does not carry the facility
 * code, so the post-commit subscriber path cannot resolve the right
 * ledger row for ADJUSTED movements. Writing the row from the handler,
 * where the facility is known from the command, keeps the ledger
 * accurate without dragging facility through every consume event.
 */
#[AsMessageHandler(bus: 'command.bus')]
final class AdjustStockHandler
{
    public function __construct(
        private readonly InventoryItems $inventoryItems,
        private readonly IdentityGenerator $ids,
        private readonly ClockInterface $clock,
        private readonly MessageBusInterface $eventBus,
        private readonly StockMovementLedger $movementLedger,
    ) {
    }

    public function __invoke(AdjustStock $command): void
    {
        if (trim($command->reason) === '') {
            throw AdjustmentReasonRequired::empty();
        }

        $subReason = $this->resolveSubReason($command->adjustmentSubReason);

        $itemId = InventoryItemId::fromString($command->itemId);
        $facility = FacilityCode::fromString($command->facilityCode);
        $target = Quantity::ofUnits($command->targetQuantityUnits);

        $item = $this->inventoryItems->byId($itemId);
        $current = $item->totalOnHandAt($facility);

        if ($target->equals($current)) {
            return;
        }

        $occurredAt = $this->clock->now();

        if ($target->units > $current->units) {
            $delta = $target->subtract($current);
            $direction = StockAdjustmentDirection::INCREASE;
            $item->receiveBatch(
                $facility,
                $delta,
                CostPerUnit::zero(),
                null,
                Comment::of($command->reason),
                $this->ids->nextStockBatchId(),
                $this->clock,
            );
        } else {
            $delta = $current->subtract($target);
            $direction = StockAdjustmentDirection::DECREASE;
            $item->consume($facility, $delta, StockMovementReason::ADJUSTMENT, $this->clock);
        }

        $this->inventoryItems->save($item);

        $this->movementLedger->recordAdjusted(
            itemId: $itemId,
            facilityCode: $facility,
            stockBatchId: null,
            quantity: $delta,
            direction: $direction,
            costPerUnit: CostPerUnit::zero(),
            recordedAt: $occurredAt,
            operatorNote: $this->formatOperatorNote($subReason, $command->reason),
        );

        foreach ($item->releaseEvents() as $event) {
            $this->eventBus->dispatch($event, [new DispatchAfterCurrentBusStamp()]);
        }
    }

    private function resolveSubReason(?string $raw): StockAdjustmentReason
    {
        if ($raw === null) {
            return StockAdjustmentReason::OTHER;
        }

        $normalized = trim($raw);
        if ($normalized === '') {
            return StockAdjustmentReason::OTHER;
        }

        $resolved = StockAdjustmentReason::tryFrom($normalized);
        if ($resolved === null) {
            throw InvalidStockAdjustmentSubReason::for($normalized);
        }

        return $resolved;
    }

    private function formatOperatorNote(StockAdjustmentReason $subReason, string $reason): string
    {
        return sprintf('[%s] %s', strtoupper($subReason->value), trim($reason));
    }
}
