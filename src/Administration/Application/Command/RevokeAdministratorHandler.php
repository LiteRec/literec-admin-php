<?php

declare(strict_types=1);

namespace App\Administration\Application\Command;

use App\Administration\Application\ActorAssembler;
use App\Administration\Domain\Administrators;
use App\Administration\Domain\Exception\AdministratorAlreadyRevoked;
use App\Administration\Domain\ValueObject\AdministratorId;
use App\Administration\Domain\ValueObject\RevocationReason;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler(bus: 'command.bus')]
final class RevokeAdministratorHandler
{
    use ReleasesAdministrationEvents;

    public function __construct(
        private readonly Administrators $administrators,
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
     * @throws AdministratorAlreadyRevoked when this administrator is
     *         already revoked.
     */
    public function __invoke(RevokeAdministrator $command): void
    {
        $administrator = $this->administrators->byId(AdministratorId::fromString($command->administratorId));
        $actor = $this->actors->fromPrimitives($command->actorKind, $command->actorId);

        $administrator->revoke(RevocationReason::of($command->reason), $actor, $this->clock);
        $this->administrators->save($administrator);

        $this->releaseAndDispatch($administrator);
    }
}
