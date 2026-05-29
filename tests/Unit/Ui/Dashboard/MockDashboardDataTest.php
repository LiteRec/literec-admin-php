<?php

declare(strict_types=1);

namespace App\Tests\Unit\Ui\Dashboard;

use App\Ui\Dashboard\DashboardData;
use App\Ui\Dashboard\EventItem;
use App\Ui\Dashboard\FacilityStatus;
use App\Ui\Dashboard\KpiCard;
use App\Ui\Dashboard\MockDashboardData;
use App\Ui\Dashboard\QuickLink;
use App\Ui\Dashboard\TransactionRow;
use App\Ui\Dashboard\TransactionStatus;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;

#[Small]
final class MockDashboardDataTest extends TestCase
{
    #[Test]
    #[TestDox('Builds four KPI cards (revenue, memberships, reservations, refunds), each with an icon and gradient.')]
    public function it_builds_the_four_documented_kpi_cards(): void
    {
        $data = $this->buildData();

        self::assertCount(4, $data->kpis);
        $labels = array_map(static fn (KpiCard $k): string => $k->label, $data->kpis);
        self::assertSame(
            ["Today's Revenue", 'Active Memberships', 'Upcoming Reservations', 'Open Refund Requests'],
            $labels,
        );
        foreach ($data->kpis as $kpi) {
            self::assertNotSame('', $kpi->icon);
            self::assertStringContainsString('gradient', $kpi->gradient);
        }
    }

    #[Test]
    #[TestDox('Recent transactions feed has at least 10 rows with realistic shape.')]
    public function recent_transactions_meet_the_minimum_count_and_shape(): void
    {
        $data = $this->buildData();

        self::assertGreaterThanOrEqual(10, count($data->recentTransactions));
        foreach ($data->recentTransactions as $row) {
            self::assertInstanceOf(TransactionRow::class, $row);
            self::assertNotSame('', $row->userName);
            self::assertStringStartsWith('$', $row->amountFormatted);
            self::assertInstanceOf(TransactionStatus::class, $row->status);
        }
    }

    #[Test]
    #[TestDox('Upcoming events sample has at least three dated entries.')]
    public function upcoming_events_have_at_least_three_entries(): void
    {
        $data = $this->buildData();

        self::assertGreaterThanOrEqual(3, count($data->upcomingEvents));
        foreach ($data->upcomingEvents as $event) {
            self::assertInstanceOf(EventItem::class, $event);
            self::assertNotSame('', $event->title);
            self::assertGreaterThanOrEqual(0, $event->attendees);
        }
    }

    #[Test]
    #[TestDox('Facility status sample lists facilities with a known badge variant and visitor count.')]
    public function facility_statuses_have_a_badge_variant_and_visitor_count(): void
    {
        $data = $this->buildData();

        self::assertGreaterThanOrEqual(3, count($data->facilityStatuses));
        foreach ($data->facilityStatuses as $facility) {
            self::assertInstanceOf(FacilityStatus::class, $facility);
            self::assertContains(
                $facility->badgeVariant,
                ['success', 'warning', 'danger', 'info', 'neutral'],
            );
            self::assertGreaterThanOrEqual(0, $facility->visitorsToday);
        }
    }

    #[Test]
    #[TestDox('Quick actions target the seven top-level nav route names, each with an icon.')]
    public function quick_links_target_the_seven_nav_categories(): void
    {
        $data = $this->buildData();

        self::assertCount(7, $data->quickLinks);
        $routes = array_map(static fn (QuickLink $l): string => $l->route, $data->quickLinks);
        self::assertSame(
            [
                'cash_register_index',
                'programs_index',
                'users_index',
                'memberships_index',
                'facilities_index',
                'reports_index',
                'communications_index',
            ],
            $routes,
        );
        foreach ($data->quickLinks as $link) {
            self::assertNotSame('', $link->icon);
        }
    }

    private function buildData(): DashboardData
    {
        $clock = new class () implements ClockInterface {
            public function now(): DateTimeImmutable
            {
                return new DateTimeImmutable('2026-05-23T12:00:00Z');
            }
        };

        return (new MockDashboardData($clock))->build();
    }
}
