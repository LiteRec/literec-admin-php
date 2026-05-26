<?php

declare(strict_types=1);

namespace App\Inventory\Application\Command;

use App\Inventory\Domain\ComboGraphResolver;
use App\Inventory\Domain\Combos;
use App\Inventory\Domain\ValueObject\ComboComponent;
use App\Inventory\Domain\ValueObject\ComboId;
use App\Inventory\Domain\ValueObject\InventoryItemId;
use App\Inventory\Domain\ValueObject\Quantity;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;

#[AsMessageHandler(bus: 'command.bus')]
final class UpdateComboComponentsHandler
{
    public function __construct(
        private readonly Combos $combos,
        private readonly ComboGraphResolver $resolver,
        private readonly ClockInterface $clock,
        private readonly MessageBusInterface $eventBus,
    ) {
    }

    public function __invoke(UpdateComboComponents $command): void
    {
        $combo = $this->combos->byId(ComboId::fromString($command->comboId));

        $components = [];
        foreach ($command->components as $row) {
            $components[] = new ComboComponent(
                InventoryItemId::fromString($row['itemId']),
                Quantity::ofUnits($row['quantityPerCombo']),
            );
        }

        $combo->replaceComponents($components, $this->resolver, $this->clock);

        $this->combos->save($combo);

        foreach ($combo->releaseEvents() as $event) {
            $this->eventBus->dispatch($event, [new DispatchAfterCurrentBusStamp()]);
        }
    }
}
