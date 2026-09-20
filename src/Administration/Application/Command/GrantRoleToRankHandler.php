<?php

declare(strict_types=1);

namespace App\Administration\Application\Command;

use App\Administration\Application\ActorAssembler;
use App\Administration\Domain\Ranks;
use App\Administration\Domain\ValueObject\RankId;
use App\Administration\Domain\ValueObject\RoleId;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;

#[AsMessageHandler(bus: 'command.bus')]
final class GrantRoleToRankHandler
{
    public function __construct(
        private readonly Ranks $ranks,
        private readonly ClockInterface $clock,
        private readonly ActorAssembler $actors,
        private readonly MessageBusInterface $eventBus,
    ) {
    }

    public function __invoke(GrantRoleToRank $command): void
    {
        $rank = $this->ranks->byId(RankId::fromString($command->rankId));
        $actor = $this->actors->fromPrimitives($command->actorKind, $command->actorId);

        $rank->grantRole(RoleId::fromString($command->roleId), $actor, $this->clock);
        $this->ranks->save($rank);

        foreach ($rank->releaseEvents() as $event) {
            $this->eventBus->dispatch($event, [new DispatchAfterCurrentBusStamp()]);
        }
    }
}
