<?php

declare(strict_types=1);

namespace App\Controller;

use App\Ui\Dashboard\MockDashboardData;
use App\Ui\Dashboard\TransactionStatus;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
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
            'statusCases' => TransactionStatus::cases(),
        ]);
    }

    /**
     * HTMX swap target for the Recent transactions status pill filter.
     * Returns only the table partial so the pill bar and table body stay in
     * sync on every filter change.
     */
    #[Route('/dashboard/_transactions', name: 'dashboard_transactions', methods: ['GET'])]
    public function transactions(Request $request): Response
    {
        $status = $this->parseStatus($request);

        return $this->render('dashboard/_transactions_table.html.twig', [
            'transactions' => $this->dashboardData->recentTransactions($status),
            'status' => $status,
            'statusCases' => TransactionStatus::cases(),
        ]);
    }

    private function parseStatus(Request $request): ?TransactionStatus
    {
        $raw = $request->query->get('status');

        return is_string($raw) && $raw !== '' ? TransactionStatus::tryFrom($raw) : null;
    }
}
