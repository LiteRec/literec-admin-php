<?php

declare(strict_types=1);

namespace App\Administration\Application\Command;

use App\Administration\Application\ActorAssembler;
use App\Administration\Domain\Exception\DuplicateRoleName;
use App\Administration\Domain\IdentityGenerator;
use App\Administration\Domain\Privilege;
use App\Administration\Domain\Role;
use App\Administration\Domain\Roles;
use App\Administration\Domain\ValueObject\PrivilegeSet;
use App\Administration\Domain\ValueObject\RoleDescription;
use App\Administration\Domain\ValueObject\RoleId;
use App\Administration\Domain\ValueObject\RoleName;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;

#[AsMessageHandler(bus: 'command.bus')]
final class DefineRoleHandler
{
    public function __construct(
        private readonly Roles $roles,
        private readonly IdentityGenerator $ids,
        private readonly ClockInterface $clock,
        private readonly ActorAssembler $actors,
        private readonly MessageBusInterface $eventBus,
    ) {
    }

    public function __invoke(DefineRole $command): RoleId
    {
        $name = RoleName::of($command->name);

        if ($this->roles->existsWithName($name)) {
            throw DuplicateRoleName::of($name->value);
        }

        $description = RoleDescription::of($command->description);
        $privileges = PrivilegeSet::of(...array_map(
            static fn (string $privilegeName): Privilege => Privilege::from($privilegeName),
            $command->privilegeNames,
        ));
        $actor = $this->actors->fromPrimitives($command->actorKind, $command->actorId);
        $id = $this->ids->nextRoleId();

        $role = Role::define($id, $name, $description, $privileges, $actor, $this->clock);
        $this->roles->add($role);

        foreach ($role->releaseEvents() as $event) {
            $this->eventBus->dispatch($event, [new DispatchAfterCurrentBusStamp()]);
        }

        return $id;
    }
}
