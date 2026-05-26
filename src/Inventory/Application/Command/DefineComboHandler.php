<?php

declare(strict_types=1);

namespace App\Inventory\Application\Command;

use App\Catalog\Domain\ValueObject\ListingId;
use App\Inventory\Domain\Combo;
use App\Inventory\Domain\ComboGraphResolver;
use App\Inventory\Domain\Combos;
use App\Inventory\Domain\Exception\ComboRequiresComponents;
use App\Inventory\Domain\IdentityGenerator;
use App\Inventory\Domain\ValueObject\ComboComponent;
use App\Inventory\Domain\ValueObject\ComboId;
use App\Inventory\Domain\ValueObject\InventoryItemId;
use App\Inventory\Domain\ValueObject\Quantity;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;

#[AsMessageHandler(bus: 'command.bus')]
final class DefineComboHandler
{
    public function __construct(
        private readonly Combos $combos,
        private readonly ComboGraphResolver $resolver,
        private readonly IdentityGenerator $ids,
        private readonly ClockInterface $clock,
        private readonly MessageBusInterface $eventBus,
    ) {
    }

    public function __invoke(DefineCombo $command): ComboId
    {
        // Guard the empty-components case before allocating an identity
        // so a malformed command does not consume a UUID from the
        // generator's audit trail.
        if ($command->components === []) {
            throw ComboRequiresComponents::empty();
        }

        $listingId = ListingId::fromString($command->listingId);

        $components = [];
        foreach ($command->components as $row) {
            $components[] = new ComboComponent(
                InventoryItemId::fromString($row['itemId']),
                Quantity::ofUnits($row['quantityPerCombo']),
            );
        }

        $comboId = $this->ids->nextComboId();
        $combo = Combo::define($comboId, $listingId, $components, $this->resolver, $this->clock);

        $this->combos->add($combo);

        foreach ($combo->releaseEvents() as $event) {
            $this->eventBus->dispatch($event, [new DispatchAfterCurrentBusStamp()]);
        }

        return $comboId;
    }
}
