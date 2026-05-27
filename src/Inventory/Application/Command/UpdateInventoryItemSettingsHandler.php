<?php

declare(strict_types=1);

namespace App\Inventory\Application\Command;

use App\Inventory\Domain\InventoryItems;
use App\Inventory\Domain\ValueObject\InventoryItemId;
use App\Inventory\Domain\ValueObject\PosColor;
use App\Inventory\Domain\ValueObject\ReorderThreshold;
use App\Inventory\Domain\ValueObject\VendorId;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;

/**
 * Applies LRA-86 Edit-dialog changes to the Inventory-side fields of
 * an existing {@see \App\Inventory\Domain\InventoryItem}. Each updater
 * on the aggregate is no-op when the new value equals the current one,
 * so dispatching this command with unchanged inputs is safe and emits
 * no spurious domain events.
 *
 * Persists once at the end via {@see InventoryItems::save()} so the
 * Doctrine optimistic-lock version increments at most once per Edit
 * submission. Released events are forwarded onto the event bus with
 * {@see DispatchAfterCurrentBusStamp} so subscribers fire only after
 * the outer write transaction commits — matching the
 * RegisterInventoryItem pattern.
 */
#[AsMessageHandler(bus: 'command.bus')]
final readonly class UpdateInventoryItemSettingsHandler
{
    public function __construct(
        private InventoryItems $inventoryItems,
        private ClockInterface $clock,
        private MessageBusInterface $eventBus,
    ) {
    }

    public function __invoke(UpdateInventoryItemSettings $command): void
    {
        $item = $this->inventoryItems->byId(InventoryItemId::fromString($command->itemId));

        $item->updatePosColor(PosColor::ofHex($command->posColorHex), $this->clock);

        $nextVendor = $command->primaryVendorId !== null
            ? VendorId::fromString($command->primaryVendorId)
            : null;
        $item->updatePrimaryVendor($nextVendor, $this->clock);

        if ($command->trackInventory) {
            $item->enableTracking($this->clock);
        } else {
            $item->disableTracking($this->clock);
        }

        if ($command->rentable) {
            $item->markRentable($this->clock);
        } else {
            $item->markNonRentable($this->clock);
        }

        $item->updateReorderThreshold(
            ReorderThreshold::ofUnits($command->reorderThresholdUnits),
            $this->clock,
        );

        $this->inventoryItems->save($item);

        foreach ($item->releaseEvents() as $event) {
            $this->eventBus->dispatch(new Envelope($event, [new DispatchAfterCurrentBusStamp()]));
        }
    }
}
