<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Public entry points for the application.
 *
 * The home route renders a placeholder until the authenticated experience
 * is wired up (LRA-9); the health route backs the container healthcheck
 * configured in LRA-3.
 */
final class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('home/index.html.twig');
    }

    #[Route('/health', name: 'app_health', methods: ['GET'])]
    public function health(): JsonResponse
    {
        return new JsonResponse(
            ['status' => 'ok'],
            Response::HTTP_OK,
            ['Cache-Control' => 'no-cache, no-store, must-revalidate'],
        );
    }
}
