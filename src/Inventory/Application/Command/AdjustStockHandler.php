<?php

declare(strict_types=1);

namespace App\Inventory\Application\Command;

use App\Inventory\Domain\Exception\AdjustmentReasonRequired;
use App\Inventory\Domain\IdentityGenerator;
use App\Inventory\Domain\InventoryItems;
use App\Inventory\Domain\ValueObject\Comment;
use App\Inventory\Domain\ValueObject\CostPerUnit;
use App\Inventory\Domain\ValueObject\FacilityCode;
use App\Inventory\Domain\ValueObject\InventoryItemId;
use App\Inventory\Domain\ValueObject\Quantity;
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
 * the synthetic batch's {@see Comment}; for negative adjustments the
 * reason currently lives only in the application log because
 * {@see App\Inventory\Domain\Event\StockMovementRecorded} does not
 * carry an operator note. A future Stock Adjustment ledger (paired
 * with LRA-83/84) will persist the reason for both directions.
 */
#[AsMessageHandler(bus: 'command.bus')]
final class AdjustStockHandler
{
    public function __construct(
        private readonly InventoryItems $inventoryItems,
        private readonly IdentityGenerator $ids,
        private readonly ClockInterface $clock,
        private readonly MessageBusInterface $eventBus,
    ) {
    }

    public function __invoke(AdjustStock $command): void
    {
        if (trim($command->reason) === '') {
            throw AdjustmentReasonRequired::empty();
        }

        $itemId = InventoryItemId::fromString($command->itemId);
        $facility = FacilityCode::fromString($command->facilityCode);
        $target = Quantity::ofUnits($command->targetQuantityUnits);

        $item = $this->inventoryItems->byId($itemId);
        $current = $item->totalOnHandAt($facility);

        if ($target->equals($current)) {
            return;
        }

        if ($target->units > $current->units) {
            $delta = $target->subtract($current);
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
            $item->consume($facility, $delta, StockMovementReason::ADJUSTMENT, $this->clock);
        }

        $this->inventoryItems->save($item);

        foreach ($item->releaseEvents() as $event) {
            $this->eventBus->dispatch($event, [new DispatchAfterCurrentBusStamp()]);
        }
    }
}
