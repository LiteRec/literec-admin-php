<?php

declare(strict_types=1);

namespace App\Administration\Application\Command;

use App\Administration\Application\ActorAssembler;
use App\Administration\Domain\Roles;
use App\Administration\Domain\ValueObject\RoleId;
use App\Administration\Domain\ValueObject\RoleName;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;

#[AsMessageHandler(bus: 'command.bus')]
final class RenameRoleHandler
{
    public function __construct(
        private readonly Roles $roles,
        private readonly ClockInterface $clock,
        private readonly ActorAssembler $actors,
        private readonly MessageBusInterface $eventBus,
    ) {
    }

    public function __invoke(RenameRole $command): void
    {
        $role = $this->roles->byId(RoleId::fromString($command->roleId));
        $actor = $this->actors->fromPrimitives($command->actorKind, $command->actorId);

        $role->rename(RoleName::of($command->name), $actor, $this->clock);
        $this->roles->save($role);

        foreach ($role->releaseEvents() as $event) {
            $this->eventBus->dispatch($event, [new DispatchAfterCurrentBusStamp()]);
        }
    }
}
