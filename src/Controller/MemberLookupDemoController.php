<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\When;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Dev/test-only demo for the reusable Member Lookup dialog (LRA-46).
 *
 * The controller is registered only under the `dev` and `test` environments
 * via repeated `#[When]` attributes from the DependencyInjection component;
 * Symfony skips the service definition entirely in prod, so the route is not
 * present in the production router and any production request to it returns
 * 404 from the route loader, not from this controller.
 *
 * The page exists so reviewers (and the LRA-46 functional tests) can drive
 * the dialog without waiting for Memberships/Rentals/Transactions to land —
 * those epics are the real consumers, but they cannot embed the dialog until
 * after this ticket ships.
 */
#[When(env: 'dev')]
#[When(env: 'test')]
final class MemberLookupDemoController extends AbstractController
{
    #[Route('/_dev/member-lookup-demo', name: 'dev_member_lookup_demo', methods: ['GET'])]
    public function __invoke(): Response
    {
        return $this->render('dev/member_lookup_demo.html.twig');
    }
}
