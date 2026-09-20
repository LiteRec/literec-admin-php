<?php

declare(strict_types=1);

namespace App\Administration\Application\Command;

use App\Administration\Application\ActorAssembler;
use App\Administration\Domain\Exception\RoleNotFound;
use App\Administration\Domain\Exception\UnknownPrivilege;
use App\Administration\Domain\PrivilegeLookup;
use App\Administration\Domain\Roles;
use App\Administration\Domain\ValueObject\RoleId;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;

#[AsMessageHandler(bus: 'command.bus')]
final class GrantPrivilegeToRoleHandler
{
    public function __construct(
        private readonly Roles $roles,
        private readonly ClockInterface $clock,
        private readonly ActorAssembler $actors,
        private readonly PrivilegeLookup $privilegeLookup,
        private readonly MessageBusInterface $eventBus,
    ) {
    }

    /**
     * @throws RoleNotFound when no role has this id.
     * @throws UnknownPrivilege when the requested privilege name does not
     *         match a catalogue case.
     */
    public function __invoke(GrantPrivilegeToRole $command): void
    {
        $role = $this->roles->byId(RoleId::fromString($command->roleId));
        $actor = $this->actors->fromPrimitives($command->actorKind, $command->actorId);

        $role->grant($this->privilegeLookup->privilegeNamed($command->privilegeName), $actor, $this->clock);
        $this->roles->save($role);

        foreach ($role->releaseEvents() as $event) {
            $this->eventBus->dispatch($event, [new DispatchAfterCurrentBusStamp()]);
        }
    }
}
