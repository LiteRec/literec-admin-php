<?php

declare(strict_types=1);

namespace App\Tests\Unit\Ui\Dashboard;

use App\Ui\Dashboard\CurrentStaffMember;
use App\Ui\Dashboard\DashboardData;
use App\Ui\Dashboard\DeltaTone;
use App\Ui\Dashboard\EventItem;
use App\Ui\Dashboard\FacilityStatus;
use App\Ui\Dashboard\KpiCard;
use App\Ui\Dashboard\KpiTint;
use App\Ui\Dashboard\MockDashboardData;
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
    #[TestDox('Greeting reflects the clock hour and the signed-in staff member\'s first name.')]
    public function greeting_reflects_the_clock_hour_and_current_staff_member(): void
    {
        $data = $this->buildData();

        self::assertSame('Good afternoon, Riley', $data->greeting);
    }

    #[Test]
    #[TestDox('The header date comes from the same injected clock as the greeting, not the PHP wall clock.')]
    public function today_comes_from_the_injected_clock(): void
    {
        $data = $this->buildData();

        self::assertEquals(new DateTimeImmutable('2026-05-23T12:00:00Z'), $data->today);
    }

    #[Test]
    #[TestDox('Builds four KPI cards (revenue, memberships, reservations, refunds), each with an icon and tint.')]
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
            self::assertInstanceOf(KpiTint::class, $kpi->tint);
            self::assertTrue($kpi->deltaText !== null || $kpi->note !== null);
        }
    }

    #[Test]
    #[TestDox('A KPI with a delta figure also carries a positive delta tone.')]
    public function a_kpi_with_a_delta_figure_carries_a_delta_tone(): void
    {
        $data = $this->buildData();

        $revenue = $data->kpis[0];
        self::assertSame('+12%', $revenue->deltaText);
        self::assertSame(DeltaTone::Positive, $revenue->deltaTone);
        self::assertSame('vs. yesterday', $revenue->note);
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
    #[TestDox('recentTransactions(status) narrows the feed to rows matching that status only.')]
    public function recent_transactions_can_be_filtered_by_status(): void
    {
        $mock = $this->buildMock();

        $filtered = $mock->recentTransactions(TransactionStatus::Pending);

        self::assertNotEmpty($filtered);
        foreach ($filtered as $row) {
            self::assertSame(TransactionStatus::Pending, $row->status);
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
            self::assertGreaterThanOrEqual(0, $event->enrolledCount);
        }
    }

    #[Test]
    #[TestDox('Facility status sample lists facilities with a known badge variant and check-in count.')]
    public function facility_statuses_have_a_badge_variant_and_checkin_count(): void
    {
        $data = $this->buildData();

        self::assertGreaterThanOrEqual(3, count($data->facilityStatuses));
        foreach ($data->facilityStatuses as $facility) {
            self::assertInstanceOf(FacilityStatus::class, $facility);
            self::assertContains(
                $facility->badgeVariant,
                ['success', 'warning', 'danger', 'info', 'neutral'],
            );
            self::assertGreaterThanOrEqual(0, $facility->checkInsToday);
        }
    }

    private function buildData(): DashboardData
    {
        return $this->buildMock()->build();
    }

    private function buildMock(): MockDashboardData
    {
        $clock = new class () implements ClockInterface {
            public function now(): DateTimeImmutable
            {
                return new DateTimeImmutable('2026-05-23T12:00:00Z');
            }
        };

        $currentStaffMember = new class () implements CurrentStaffMember {
            public function firstName(): string
            {
                return 'Riley';
            }
        };

        return new MockDashboardData($clock, $currentStaffMember);
    }
}
