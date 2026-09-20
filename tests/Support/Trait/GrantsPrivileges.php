<?php

declare(strict_types=1);

namespace App\Tests\Support\Trait;

use App\Administration\Application\Command\DefineRank;
use App\Administration\Application\Command\DefineRole;
use App\Administration\Application\Command\GrantAdministrator;
use App\Administration\Application\Command\GrantRoleToRank;
use App\Administration\Domain\Privilege;
use App\Administration\Domain\ValueObject\ActorKind;
use App\Administration\Domain\ValueObject\AdministratorId;
use App\Administration\Domain\ValueObject\RankId;
use App\Administration\Domain\ValueObject\RoleId;
use App\Shared\Infrastructure\Fixtures\HandledResult;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Functional-test helper (LRA-270): registers and signs in a fresh user
 * (via {@see SignsInUsers}), then grants them administrator status
 * through a rank carrying exactly one privilege, via a dedicated role.
 * Extracted up front rather than inlined per test — SonarCloud's
 * new-code duplication threshold is 3% and near-identical functional
 * tests exercising the granted-then-revoked scenario would otherwise
 * cross it, same lesson as the LRA-87 SeedsInventoryItemForUi trait.
 */
trait GrantsPrivileges
{
    use SignsInUsers;

    /**
     * @return array{administratorId: AdministratorId, roleId: RoleId}
     */
    private function signInAdministratorWithPrivilege(
        KernelBrowser $client,
        string $username,
        Privilege $privilege,
    ): array {
        $bus = static::getContainer()->get(MessageBusInterface::class);

        $userId = $this->registerAndLogIn($client, $username, self::TEST_PASSWORD);

        $rankId = HandledResult::from(
            $bus->dispatch(new DefineRank(sprintf('%s Rank', $username), 50, ActorKind::System->value)),
            RankId::class,
        );
        $roleId = HandledResult::from(
            $bus->dispatch(new DefineRole(
                sprintf('%s Role', $username),
                '',
                [$privilege->value],
                ActorKind::System->value,
            )),
            RoleId::class,
        );
        $bus->dispatch(new GrantRoleToRank($rankId->value, $roleId->value, ActorKind::System->value));
        $administratorId = HandledResult::from(
            $bus->dispatch(new GrantAdministrator($userId->value, $rankId->value, ActorKind::System->value)),
            AdministratorId::class,
        );

        return ['administratorId' => $administratorId, 'roleId' => $roleId];
    }
}
