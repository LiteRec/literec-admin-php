<?php

declare(strict_types=1);

namespace App\Administration\Application\Command;

use App\Administration\Application\ActorAssembler;
use App\Administration\Domain\Exception\DuplicateRankName;
use App\Administration\Domain\IdentityGenerator;
use App\Administration\Domain\Rank;
use App\Administration\Domain\Ranks;
use App\Administration\Domain\ValueObject\AssignedRoles;
use App\Administration\Domain\ValueObject\RankId;
use App\Administration\Domain\ValueObject\RankName;
use App\Administration\Domain\ValueObject\SeniorityLevel;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;

#[AsMessageHandler(bus: 'command.bus')]
final class DefineRankHandler
{
    public function __construct(
        private readonly Ranks $ranks,
        private readonly IdentityGenerator $ids,
        private readonly ClockInterface $clock,
        private readonly ActorAssembler $actors,
        private readonly MessageBusInterface $eventBus,
    ) {
    }

    /**
     * @throws DuplicateRankName when a rank already holds this name.
     */
    public function __invoke(DefineRank $command): RankId
    {
        $name = RankName::of($command->name);

        if ($this->ranks->existsWithName($name)) {
            throw DuplicateRankName::of($name->value);
        }

        $seniority = SeniorityLevel::of($command->seniority);
        $actor = $this->actors->fromPrimitives($command->actorKind, $command->actorId);
        $id = $this->ids->nextRankId();

        $rank = Rank::define($id, $name, $seniority, AssignedRoles::none(), $actor, $this->clock);
        $this->ranks->add($rank);

        foreach ($rank->releaseEvents() as $event) {
            $this->eventBus->dispatch($event, [new DispatchAfterCurrentBusStamp()]);
        }

        return $id;
    }
}
