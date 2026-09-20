<?php

declare(strict_types=1);

namespace App\Administration\Infrastructure\Http\Controller;

use App\Administration\Application\Query\GetGrantedPrivileges;
use App\Administration\Application\Query\View\GrantedPrivilegesView;
use LogicException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * HTTP adapter for the LRA-270 read path: which privileges does the
 * currently authenticated sign-in account hold. The UI reads this list
 * to decide what to render — the firewall's ROLE_USER requirement is the
 * only gate here, deliberately no `#[IsGranted]` on a specific
 * privilege, since finding out you have none is exactly what an
 * administrator with no privileges needs to be able to do.
 */
final class GrantedPrivilegesController extends AbstractController
{
    use HandleTrait {
        handle as private dispatchQuery;
    }

    public function __construct(MessageBusInterface $queryBus)
    {
        $this->messageBus = $queryBus;
    }

    #[Route('/admin/administration/privileges/granted', name: 'administration_granted_privileges', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        $result = $this->dispatchQuery(new GetGrantedPrivileges());

        if (!$result instanceof GrantedPrivilegesView) {
            throw new LogicException(sprintf(
                'GetGrantedPrivileges handler returned %s, expected %s.',
                get_debug_type($result),
                GrantedPrivilegesView::class,
            ));
        }

        return $this->json(['privileges' => $result->privileges]);
    }
}
