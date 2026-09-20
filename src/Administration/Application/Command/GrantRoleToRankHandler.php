<?php

declare(strict_types=1);

namespace App\Administration\Application\Command;

use App\Administration\Application\ActorAssembler;
use App\Administration\Domain\Exception\RoleIsRetired;
use App\Administration\Domain\Exception\RoleNotFound;
use App\Administration\Domain\Ranks;
use App\Administration\Domain\Roles;
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
        private readonly Roles $roles,
        private readonly ClockInterface $clock,
        private readonly ActorAssembler $actors,
        private readonly MessageBusInterface $eventBus,
    ) {
    }

    /**
     * @throws RoleNotFound when $command->roleId does not name an existing
     *         role. The rank-roles join table carries no foreign key to
     *         administration_roles (a cross-table constraint would couple
     *         this context's two migration orderings — see the migration's
     *         docblock), so this lookup is what stops an orphaned
     *         assignment from ever being written, rather than relying on
     *         the schema to reject it.
     * @throws RoleIsRetired when the role exists but has already been
     *         retired. {@see Roles::byId()} returns a retired role
     *         unchanged — existence and liveness are different
     *         questions — so this handler checks both before granting.
     */
    public function __invoke(GrantRoleToRank $command): void
    {
        $rank = $this->ranks->byId(RankId::fromString($command->rankId));
        $roleId = RoleId::fromString($command->roleId);
        $role = $this->roles->byId($roleId);

        if ($role->isRetired()) {
            throw RoleIsRetired::for($roleId);
        }

        $actor = $this->actors->fromPrimitives($command->actorKind, $command->actorId);

        $rank->grantRole($roleId, $actor, $this->clock);
        $this->ranks->save($rank);

        foreach ($rank->releaseEvents() as $event) {
            $this->eventBus->dispatch($event, [new DispatchAfterCurrentBusStamp()]);
        }
    }
}
