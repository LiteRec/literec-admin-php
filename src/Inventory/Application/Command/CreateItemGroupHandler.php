<?php

declare(strict_types=1);

namespace App\Inventory\Application\Command;

use App\Inventory\Domain\IdentityGenerator;
use App\Inventory\Domain\ItemGroup;
use App\Inventory\Domain\ItemGroups;
use App\Inventory\Domain\ValueObject\FacilityCode;
use App\Inventory\Domain\ValueObject\FacilityScope;
use App\Inventory\Domain\ValueObject\ItemGroupId;
use App\Inventory\Domain\ValueObject\ItemGroupName;
use App\Inventory\Domain\ValueObject\PosColor;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;

#[AsMessageHandler(bus: 'command.bus')]
final class CreateItemGroupHandler
{
    public function __construct(
        private readonly ItemGroups $itemGroups,
        private readonly IdentityGenerator $ids,
        private readonly ClockInterface $clock,
        private readonly MessageBusInterface $eventBus,
    ) {
    }

    public function __invoke(CreateItemGroup $command): ItemGroupId
    {
        $name = ItemGroupName::of($command->name);
        $color = PosColor::ofHex($command->colorHex);

        $scope = $command->facilityCodes === []
            ? FacilityScope::all()
            : FacilityScope::ofFacilities(
                array_map(
                    static fn (string $code): FacilityCode => FacilityCode::fromString($code),
                    $command->facilityCodes,
                ),
            );

        $id = $this->ids->nextItemGroupId();
        $group = ItemGroup::create($id, $name, $color, $scope, $this->clock);

        $this->itemGroups->add($group);

        foreach ($group->releaseEvents() as $event) {
            $this->eventBus->dispatch($event, [new DispatchAfterCurrentBusStamp()]);
        }

        return $id;
    }
}
