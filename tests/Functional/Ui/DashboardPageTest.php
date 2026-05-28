<?php

declare(strict_types=1);

namespace App\Tests\Functional\Ui;

use App\Tests\Support\Trait\SignsInUsers;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[Large]
#[Group('database')]
final class DashboardPageTest extends WebTestCase
{
    use SignsInUsers;

    private const string TEST_USERNAME = 'dashboard_e2e';

    #[Test]
    #[TestDox('Signed-in staff land on a populated Admin Dashboard.')]
    public function dashboard_renders_kpis_transactions_schedule_and_quick_links(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);

        $crawler = $client->request('GET', '/dashboard');
        self::assertResponseIsSuccessful();

        self::assertSelectorTextContains('main h1', 'Admin Dashboard');

        $kpiLabels = $crawler
            ->filter('[aria-labelledby="kpi-heading"] [data-gsap="card"] p:first-child')
            ->each(static fn ($n): string => trim($n->text()));
        self::assertSame(
            ["Today's Revenue", 'Active Memberships', 'Upcoming Reservations', 'Open Refund Requests'],
            $kpiLabels,
        );

        $transactionRows = $crawler->filter('[aria-labelledby="transactions-heading"] ul > li')->count();
        self::assertGreaterThanOrEqual(10, $transactionRows);

        $scheduleRows = $crawler->filter('[aria-labelledby="schedule-heading"] ul > li')->count();
        self::assertGreaterThanOrEqual(5, $scheduleRows);

        $quickLinks = $crawler->filter('[aria-labelledby="quicklinks-heading"] a')->count();
        self::assertSame(7, $quickLinks);
    }
}
