<?php

declare(strict_types=1);

namespace App\Inventory\Application\Command;

use App\Inventory\Domain\Combos;
use App\Inventory\Domain\ValueObject\ComboId;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;

#[AsMessageHandler(bus: 'command.bus')]
final class ArchiveComboHandler
{
    public function __construct(
        private readonly Combos $combos,
        private readonly ClockInterface $clock,
        private readonly MessageBusInterface $eventBus,
    ) {
    }

    public function __invoke(ArchiveCombo $command): void
    {
        $combo = $this->combos->byId(ComboId::fromString($command->comboId));

        $combo->archive($this->clock);

        $this->combos->save($combo);

        foreach ($combo->releaseEvents() as $event) {
            $this->eventBus->dispatch($event, [new DispatchAfterCurrentBusStamp()]);
        }
    }
}
