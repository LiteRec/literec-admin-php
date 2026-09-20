<?php

declare(strict_types=1);

namespace App\Administration\Application\Command;

use App\Administration\Application\ActorAssembler;
use App\Administration\Domain\Administrators;
use App\Administration\Domain\Exception\RoleIsRetired;
use App\Administration\Domain\Exception\RoleNotFound;
use App\Administration\Domain\Roles;
use App\Administration\Domain\ValueObject\AdministratorId;
use App\Administration\Domain\ValueObject\RoleId;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler(bus: 'command.bus')]
final class AssignRoleToAdministratorHandler
{
    use ReleasesAdministrationEvents;

    public function __construct(
        private readonly Administrators $administrators,
        private readonly Roles $roles,
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
     * @throws RoleNotFound when $command->roleId does not name an
     *         existing role — administration_administrator_roles carries
     *         no foreign key to administration_roles (Role is a separate
     *         aggregate), so this lookup is what stops an orphaned
     *         assignment from ever being written, mirroring
     *         {@see \App\Administration\Application\Command\GrantRoleToRankHandler}.
     * @throws RoleIsRetired when the role exists but has already been
     *         retired. {@see Roles::byId()} returns a retired role
     *         unchanged — existence and liveness are different
     *         questions — so this handler checks both before assigning.
     */
    public function __invoke(AssignRoleToAdministrator $command): void
    {
        $roleId = RoleId::fromString($command->roleId);
        $role = $this->roles->byId($roleId);

        if ($role->isRetired()) {
            throw RoleIsRetired::for($roleId);
        }

        $administrator = $this->administrators->byId(AdministratorId::fromString($command->administratorId));
        $actor = $this->actors->fromPrimitives($command->actorKind, $command->actorId);

        $administrator->assignRole($roleId, $actor, $this->clock);
        $this->administrators->save($administrator);

        $this->releaseAndDispatch($administrator);
    }
}
