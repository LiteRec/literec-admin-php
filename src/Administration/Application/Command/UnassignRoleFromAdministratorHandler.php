<?php

declare(strict_types=1);

namespace App\Administration\Application\Command;

use App\Administration\Application\ActorAssembler;
use App\Administration\Domain\Administrators;
use App\Administration\Domain\ValueObject\AdministratorId;
use App\Administration\Domain\ValueObject\RoleId;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * No existence/liveness guard on $command->roleId, unlike
 * {@see AssignRoleToAdministratorHandler}: removing a reference is always
 * safe regardless of the referenced role's current state, mirroring
 * {@see RevokeRoleFromRankHandler}.
 */
#[AsMessageHandler(bus: 'command.bus')]
final class UnassignRoleFromAdministratorHandler
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

    public function __invoke(UnassignRoleFromAdministrator $command): void
    {
        $administrator = $this->administrators->byId(AdministratorId::fromString($command->administratorId));
        $actor = $this->actors->fromPrimitives($command->actorKind, $command->actorId);

        $administrator->unassignRole(RoleId::fromString($command->roleId), $actor, $this->clock);
        $this->administrators->save($administrator);

        $this->releaseAndDispatch($administrator);
    }
}
