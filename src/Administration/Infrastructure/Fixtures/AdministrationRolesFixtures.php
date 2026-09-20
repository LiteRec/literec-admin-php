<?php

declare(strict_types=1);

namespace App\Administration\Infrastructure\Fixtures;

use App\Administration\Application\Command\DefineRole;
use App\Administration\Domain\Privilege;
use App\Administration\Domain\ValueObject\ActorKind;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * A small deterministic seed set for tests, dispatching DefineRole
 * commands through the bus — same treatment as
 * {@see \App\Users\Infrastructure\Fixtures\UsersFixtures}.
 *
 * Explicitly not the product default role set, which is LRA-272. The
 * acting actor is Actor::system() (the installer/seed process), threaded
 * through the command DTO's primitive ActorKind/actorId pair.
 */
final class AdministrationRolesFixtures extends Fixture implements FixtureGroupInterface
{
    public const string FRONT_DESK_ROLE_NAME = 'Front Desk';
    public const string FACILITY_MANAGER_ROLE_NAME = 'Facility Manager';

    public function __construct(
        private readonly MessageBusInterface $commandBus,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $this->dispatch(new DefineRole(
            self::FRONT_DESK_ROLE_NAME,
            'Front-of-house point-of-sale operations.',
            [Privilege::SellMemberships->value, Privilege::ViewUsers->value],
            ActorKind::System->value,
        ));

        $this->dispatch(new DefineRole(
            self::FACILITY_MANAGER_ROLE_NAME,
            'Manages facility inventory and administrator ranks.',
            [Privilege::ManageAdminRanks->value, Privilege::ViewInventory->value, Privilege::EditInventory->value],
            ActorKind::System->value,
        ));
    }

    public static function getGroups(): array
    {
        return ['test'];
    }

    private function dispatch(DefineRole $command): void
    {
        // DefineRole is not routed to an async transport, so dispatch()
        // runs the handler inline and any exception surfaces here.
        $this->commandBus->dispatch($command);
    }
}
