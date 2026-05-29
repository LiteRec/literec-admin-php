<?php

declare(strict_types=1);

namespace App\Ui\Dashboard;

use DateInterval;
use Psr\Clock\ClockInterface;

/**
 * Builds the mock DashboardData consumed by the staff Admin Dashboard.
 * Every value is hand-picked to look realistic so designers and product
 * stakeholders can review layout and visual hierarchy before the real
 * data sources are wired up. The injected Clock keeps timestamps
 * relative to "now" without ever calling new DateTimeImmutable() in
 * application code.
 */
final readonly class MockDashboardData
{
    public function __construct(private ClockInterface $clock)
    {
    }

    public function build(): DashboardData
    {
        return new DashboardData(
            kpis: $this->buildKpis(),
            recentTransactions: $this->buildRecentTransactions(),
            upcomingEvents: $this->buildUpcomingEvents(),
            facilityStatuses: $this->buildFacilityStatuses(),
            quickLinks: $this->buildQuickLinks(),
        );
    }

    /**
     * @return list<KpiCard>
     */
    private function buildKpis(): array
    {
        return [
            new KpiCard(
                "Today's Revenue",
                '$4,182.50',
                'money',
                'linear-gradient(135deg,#8a6508,#5e4604)',
                '+12% vs. yesterday',
            ),
            new KpiCard(
                'Active Memberships',
                '1,847',
                'users',
                'linear-gradient(135deg,#1c3d5a,#0f2840)',
                '+23 this week',
            ),
            new KpiCard(
                'Upcoming Reservations',
                '34',
                'calendar',
                'linear-gradient(135deg,#2e5847,#1f3d31)',
                'next 7 days',
            ),
            new KpiCard(
                'Open Refund Requests',
                '6',
                'tag',
                'linear-gradient(135deg,#3a6a8f,#27506e)',
                '2 awaiting review',
            ),
        ];
    }

    /**
     * @return list<EventItem>
     */
    private function buildUpcomingEvents(): array
    {
        return [
            new EventItem('14', 'Jun', 'Summer Camp Kickoff', 'Community Center', 128),
            new EventItem('17', 'Jun', 'Adult Soccer League', 'Field House', 96),
            new EventItem('21', 'Jun', 'Family Swim Night', 'Aquatics Center', 210),
        ];
    }

    /**
     * @return list<FacilityStatus>
     */
    private function buildFacilityStatuses(): array
    {
        return [
            new FacilityStatus('Community Center', 'Open', 'success', 847),
            new FacilityStatus('Aquatics Center', 'Busy', 'info', 512),
            new FacilityStatus('Field House', 'Maintenance', 'warning', 203),
        ];
    }

    /**
     * @return list<QuickLink>
     */
    private function buildQuickLinks(): array
    {
        return [
            new QuickLink('Cash Register', 'cash_register_index', 'cart'),
            new QuickLink('Programs', 'programs_index', 'calendar'),
            new QuickLink('Users', 'users_index', 'users'),
            new QuickLink('Memberships', 'memberships_index', 'ticket'),
            new QuickLink('Facilities', 'facilities_index', 'tree'),
            new QuickLink('Reports', 'reports_index', 'print'),
            new QuickLink('Communications', 'communications_index', 'bell'),
        ];
    }

    /**
     * @return list<TransactionRow>
     */
    private function buildRecentTransactions(): array
    {
        $now = $this->clock->now();

        $rows = [
            ['minus' => 'PT8M', 'user' => 'Alex Morgan', 'amount' => 4500,
                'method' => 'Visa •••• 4242', 'status' => TransactionStatus::Succeeded],
            ['minus' => 'PT22M', 'user' => 'Brenda Liu', 'amount' => 12000,
                'method' => 'Mastercard •••• 9911', 'status' => TransactionStatus::Succeeded],
            ['minus' => 'PT47M', 'user' => 'Carlos Reyes', 'amount' => 2500,
                'method' => 'Cash', 'status' => TransactionStatus::Succeeded],
            ['minus' => 'PT1H5M', 'user' => 'Dana Whitaker', 'amount' => 6800,
                'method' => 'EFT', 'status' => TransactionStatus::Pending],
            ['minus' => 'PT1H32M', 'user' => 'Evan Kowalski', 'amount' => 1500,
                'method' => 'Gift Card', 'status' => TransactionStatus::Succeeded],
            ['minus' => 'PT2H10M', 'user' => 'Fatima Idris', 'amount' => 9900,
                'method' => 'Visa •••• 0001', 'status' => TransactionStatus::Refunded],
            ['minus' => 'PT2H44M', 'user' => 'Gabriel Ortiz', 'amount' => 3500,
                'method' => 'Check #2041', 'status' => TransactionStatus::Succeeded],
            ['minus' => 'PT3H8M', 'user' => 'Hana Park', 'amount' => 7500,
                'method' => 'Mastercard •••• 4488', 'status' => TransactionStatus::Failed],
            ['minus' => 'PT3H51M', 'user' => 'Ivan Brooks', 'amount' => 5000,
                'method' => 'Cash', 'status' => TransactionStatus::Succeeded],
            ['minus' => 'PT4H20M', 'user' => 'Jordan Patel', 'amount' => 11000,
                'method' => 'EFT', 'status' => TransactionStatus::Pending],
        ];

        $out = [];
        foreach ($rows as $row) {
            $out[] = new TransactionRow(
                at: $now->sub(new DateInterval($row['minus'])),
                userName: $row['user'],
                amountFormatted: $this->formatCents($row['amount']),
                method: $row['method'],
                status: $row['status'],
            );
        }

        return $out;
    }

    private function formatCents(int $cents): string
    {
        return '$' . number_format($cents / 100, 2);
    }
}
