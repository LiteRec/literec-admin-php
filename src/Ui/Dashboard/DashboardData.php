<?php

declare(strict_types=1);

namespace App\Ui\Dashboard;

/**
 * Aggregate of every block the dashboard renders: KPI cards, the recent
 * transactions feed, today's schedule, and quick-link tiles. Immutable
 * by construction so the template can rely on stable shape.
 */
final readonly class DashboardData
{
    /**
     * @param list<KpiCard> $kpis
     * @param list<TransactionRow> $recentTransactions
     * @param list<ScheduleItem> $todaysSchedule
     * @param list<QuickLink> $quickLinks
     */
    public function __construct(
        public array $kpis,
        public array $recentTransactions,
        public array $todaysSchedule,
        public array $quickLinks,
    ) {
    }
}
