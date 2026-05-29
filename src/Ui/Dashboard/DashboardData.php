<?php

declare(strict_types=1);

namespace App\Ui\Dashboard;

/**
 * Aggregate of every block the dashboard renders: KPI cards, the recent
 * activity feed (transactions), upcoming events, facility status, and
 * quick-action tiles. Immutable by construction so the template can rely on a
 * stable shape.
 */
final readonly class DashboardData
{
    /**
     * @param list<KpiCard> $kpis
     * @param list<TransactionRow> $recentTransactions
     * @param list<EventItem> $upcomingEvents
     * @param list<FacilityStatus> $facilityStatuses
     * @param list<QuickLink> $quickLinks
     */
    public function __construct(
        public array $kpis,
        public array $recentTransactions,
        public array $upcomingEvents,
        public array $facilityStatuses,
        public array $quickLinks,
    ) {
    }
}
