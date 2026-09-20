<?php

declare(strict_types=1);

namespace App\Administration\Infrastructure\Http\Controller;

use App\Administration\Application\Query\ListRoles;
use App\Administration\Application\Query\View\RoleListPage;
use App\Administration\Application\Query\View\RoleSummaryView;
use App\Administration\Domain\Privilege;
use LogicException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * JSON API for the role-administration list (LRA-270). The first real
 * consumer of a catalogue {@see Privilege} via `#[IsGranted]` — proof
 * that {@see \App\Administration\Infrastructure\Security\PrivilegeVoter}
 * enforces server-side, per-request resolution rather than trusting
 * anything the client sends. Adopting catalogue privileges across the
 * REST of the application is LRA-275's job, not this one.
 */
final class RoleListController extends AbstractController
{
    use HandleTrait {
        handle as private dispatchQuery;
    }

    public function __construct(MessageBusInterface $queryBus)
    {
        $this->messageBus = $queryBus;
    }

    #[Route('/admin/administration/roles', name: 'administration_roles_list', methods: ['GET'])]
    #[IsGranted(Privilege::ManageAdminRanks->value)]
    public function __invoke(): JsonResponse
    {
        $result = $this->dispatchQuery(new ListRoles());

        if (!$result instanceof RoleListPage) {
            throw new LogicException(sprintf(
                'ListRoles handler returned %s, expected %s.',
                get_debug_type($result),
                RoleListPage::class,
            ));
        }

        return $this->json([
            'roles' => array_map(
                static fn (RoleSummaryView $role): array => [
                    'id' => $role->id,
                    'name' => $role->name,
                    'privilegeCount' => $role->privilegeCount,
                    'retired' => $role->retired,
                ],
                $result->roles,
            ),
        ]);
    }
}
