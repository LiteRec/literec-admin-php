<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Public entry points for the application.
 *
 * The home route sends authenticated users to the dashboard; the firewall
 * redirects anonymous users to the login page before the controller runs.
 * The health route backs the container healthcheck configured in LRA-3.
 */
final class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function index(): RedirectResponse
    {
        return $this->redirectToRoute('app_dashboard');
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
