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
            kpis: [
                new KpiCard("Today's Revenue", '$4,182.50', '+12% vs. yesterday'),
                new KpiCard('Active Memberships', '1,847', '+23 this week'),
                new KpiCard('Upcoming Reservations', '34', 'next 7 days'),
                new KpiCard('Open Refund Requests', '6', '2 awaiting review'),
            ],
            recentTransactions: $this->buildRecentTransactions(),
            todaysSchedule: [
                new ScheduleItem('9:00 AM', 'Court A', 'Eastside Pickleball Club'),
                new ScheduleItem('10:30 AM', 'Pool', 'Otter Swim Lessons'),
                new ScheduleItem('12:00 PM', 'Gymnasium', 'Senior Basketball Open Play'),
                new ScheduleItem('2:00 PM', 'Studio 2', 'Tuesday Yoga'),
                new ScheduleItem('3:30 PM', 'Court B', 'Junior Tennis Camp'),
                new ScheduleItem('5:00 PM', 'Conf. Rm. 1', 'Membership Committee'),
            ],
            quickLinks: [
                new QuickLink('Cash Register', 'cash_register_index'),
                new QuickLink('Programs', 'programs_index'),
                new QuickLink('Users', 'users_index'),
                new QuickLink('Memberships', 'memberships_index'),
                new QuickLink('Facilities', 'facilities_index'),
                new QuickLink('Reports', 'reports_index'),
                new QuickLink('Communications', 'communications_index'),
            ],
        );
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
