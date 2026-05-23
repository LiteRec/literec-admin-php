<?php

declare(strict_types=1);

namespace App\Controller;

use App\Ui\Dashboard\MockDashboardData;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Staff Admin Dashboard — the post-login landing page. Renders entirely
 * from mock data sourced from MockDashboardData; real data sources will
 * arrive as their bounded contexts come online.
 */
final class DashboardController extends AbstractController
{
    public function __construct(private readonly MockDashboardData $dashboardData)
    {
    }

    #[Route('/dashboard', name: 'app_dashboard', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('dashboard/index.html.twig', [
            'dashboard' => $this->dashboardData->build(),
        ]);
    }
}
