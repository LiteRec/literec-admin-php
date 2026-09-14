<?php

declare(strict_types=1);

namespace App\Ui\Dashboard;

use DateTimeImmutable;

/**
 * Aggregate of every block the dashboard renders: the page header greeting,
 * KPI cards, the recent transactions table, upcoming events, and facility
 * status. Immutable by construction so the template can rely on a stable
 * shape.
 */
final readonly class DashboardData
{
    /**
     * @param list<KpiCard> $kpis
     * @param list<TransactionRow> $recentTransactions
     * @param list<EventItem> $upcomingEvents
     * @param list<FacilityStatus> $facilityStatuses
     */
    public function __construct(
        public string $greeting,
        public DateTimeImmutable $today,
        public array $kpis,
        public array $recentTransactions,
        public array $upcomingEvents,
        public array $facilityStatuses,
    ) {
    }
}
