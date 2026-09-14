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
 * application code; the injected CurrentStaffMember keeps the greeting
 * off Symfony Security directly.
 */
final readonly class MockDashboardData
{
    public function __construct(
        private ClockInterface $clock,
        private CurrentStaffMember $currentStaffMember,
    ) {
    }

    public function build(): DashboardData
    {
        return new DashboardData(
            greeting: $this->buildGreeting(),
            today: $this->clock->now(),
            kpis: $this->buildKpis(),
            recentTransactions: $this->recentTransactions(),
            upcomingEvents: $this->buildUpcomingEvents(),
            facilityStatuses: $this->buildFacilityStatuses(),
        );
    }

    /**
     * The Recent transactions table's rows, optionally narrowed to a single
     * status for the dashboard_transactions HTMX filter.
     *
     * @return list<TransactionRow>
     */
    public function recentTransactions(?TransactionStatus $status = null): array
    {
        $rows = $this->buildRecentTransactions();

        if ($status === null) {
            return $rows;
        }

        return array_values(array_filter(
            $rows,
            static fn (TransactionRow $row): bool => $row->status === $status,
        ));
    }

    /**
     * Time-of-day greeting paired with the signed-in staff member's first
     * name.
     */
    private function buildGreeting(): string
    {
        $hour = (int) $this->clock->now()->format('G');
        $timeOfDay = match (true) {
            $hour < 12 => 'morning',
            $hour < 17 => 'afternoon',
            default => 'evening',
        };

        return "Good {$timeOfDay}, {$this->currentStaffMember->firstName()}";
    }

    /**
     * @return list<KpiCard>
     */
    private function buildKpis(): array
    {
        return [
            new KpiCard(
                label: "Today's Revenue",
                value: '$4,182.50',
                icon: 'money',
                tint: KpiTint::Accent,
                deltaText: '+12%',
                deltaTone: DeltaTone::Positive,
                note: 'vs. yesterday',
            ),
            new KpiCard(
                label: 'Active Memberships',
                value: '1,847',
                icon: 'users',
                tint: KpiTint::Sage,
                deltaText: '+23',
                deltaTone: DeltaTone::Positive,
                note: 'this week',
            ),
            new KpiCard(
                label: 'Upcoming Reservations',
                value: '34',
                icon: 'calendar',
                tint: KpiTint::Accent,
                note: 'next 7 days',
            ),
            new KpiCard(
                label: 'Open Refund Requests',
                value: '6',
                icon: 'tag',
                tint: KpiTint::Sage,
                note: '2 awaiting review',
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
            new FacilityStatus('Aquatics Center', 'Busy', 'warning', 512),
            new FacilityStatus('Field House', 'Maintenance', 'neutral', 203),
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
