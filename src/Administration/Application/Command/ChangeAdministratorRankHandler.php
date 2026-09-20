<?php

declare(strict_types=1);

namespace App\Administration\Application\Command;

use App\Administration\Application\ActorAssembler;
use App\Administration\Domain\Administrators;
use App\Administration\Domain\Exception\RankIsRetired;
use App\Administration\Domain\Exception\RankNotFound;
use App\Administration\Domain\Ranks;
use App\Administration\Domain\ValueObject\AdministratorId;
use App\Administration\Domain\ValueObject\RankId;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler(bus: 'command.bus')]
final class ChangeAdministratorRankHandler
{
    use ReleasesAdministrationEvents;

    public function __construct(
        private readonly Administrators $administrators,
        private readonly Ranks $ranks,
        private readonly ClockInterface $clock,
        private readonly ActorAssembler $actors,
        private readonly MessageBusInterface $eventBus,
    ) {
    }

    private function eventBus(): MessageBusInterface // NOSONAR
    {
        return $this->eventBus;
    }

    /**
     * @throws RankNotFound when $command->rankId does not name an
     *         existing rank — see {@see GrantAdministratorHandler} for
     *         why this lookup, not the schema, is what stops an orphaned
     *         reference.
     * @throws RankIsRetired when the rank exists but has already been
     *         retired. {@see Ranks::byId()} returns a retired rank
     *         unchanged — existence and liveness are different
     *         questions — so this handler checks both before reassigning.
     */
    public function __invoke(ChangeAdministratorRank $command): void
    {
        $rankId = RankId::fromString($command->rankId);
        $rank = $this->ranks->byId($rankId);

        if ($rank->isRetired()) {
            throw RankIsRetired::for($rankId);
        }

        $administrator = $this->administrators->byId(AdministratorId::fromString($command->administratorId));
        $actor = $this->actors->fromPrimitives($command->actorKind, $command->actorId);

        $administrator->changeRankTo($rankId, $actor, $this->clock);
        $this->administrators->save($administrator);

        $this->releaseAndDispatch($administrator);
    }
}
