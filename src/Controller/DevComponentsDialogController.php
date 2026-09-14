<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\When;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * HTMX fragment backing the dialog demo on the /_dev/components showcase
 * (LRA-185). Kept as its own single-action controller/route, mirroring how
 * real dialogs (e.g. inventory combo create/edit) are fetched on demand
 * rather than rendered open by default — see `dev/_components_dialog.html.twig`.
 */
#[When(env: 'dev')]
#[When(env: 'test')]
final class DevComponentsDialogController extends AbstractController
{
    #[Route('/_dev/components/dialog', name: 'dev_components_dialog', methods: ['GET'])]
    public function __invoke(): Response
    {
        return $this->render('dev/_components_dialog.html.twig');
    }
}
