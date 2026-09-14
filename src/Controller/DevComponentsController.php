<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\When;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Dev/test-only showcase of the Organic `lr-*` component layer (LRA-185).
 *
 * The controller is registered only under the `dev` and `test` environments
 * via repeated `#[When]` attributes, mirroring {@see MemberLookupDemoController}:
 * Symfony skips the service definition entirely in prod, so the route is not
 * present in the production router.
 *
 * The page renders every restyled `lr-*` component side by side so reviewers
 * can check the Organic treatment (pill controls, sand context surfaces,
 * ramp-tinted tags, the themed table and dialog) in both the light and dark
 * theme via the header's existing theme toggle, without needing a real
 * feature screen for every component.
 */
#[When(env: 'dev')]
#[When(env: 'test')]
final class DevComponentsController extends AbstractController
{
    #[Route('/_dev/components', name: 'dev_components', methods: ['GET'])]
    public function __invoke(): Response
    {
        return $this->render('dev/components.html.twig');
    }
}
