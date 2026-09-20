<?php

declare(strict_types=1);

namespace App\Administration\Application\Command;

use App\Administration\Application\ActorAssembler;
use App\Administration\Domain\Administrators;
use App\Administration\Domain\Exception\AdministratorAlreadyActive;
use App\Administration\Domain\IdentityGenerator;
use App\Administration\Domain\ValueObject\AdministratorId;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler(bus: 'command.bus')]
final class RegrantAdministratorHandler
{
    use ReleasesAdministrationEvents;

    public function __construct(
        private readonly Administrators $administrators,
        private readonly IdentityGenerator $ids,
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
     * @throws AdministratorAlreadyActive when this administrator is
     *         already active.
     */
    public function __invoke(RegrantAdministrator $command): void
    {
        $administrator = $this->administrators->byId(AdministratorId::fromString($command->administratorId));
        $actor = $this->actors->fromPrimitives($command->actorKind, $command->actorId);

        $administrator->regrant($this->ids->nextTenureId(), $actor, $this->clock);
        $this->administrators->save($administrator);

        $this->releaseAndDispatch($administrator);
    }
}
