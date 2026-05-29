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
    #[TestDox('Signed-in staff land on the redesigned Admin Dashboard with all Eagleton sections.')]
    public function dashboard_renders_the_eagleton_sections(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);

        $crawler = $client->request('GET', '/dashboard');
        self::assertResponseIsSuccessful();

        self::assertSelectorTextContains('main h1', 'Admin Dashboard');
        self::assertSelectorTextContains('.lr-pagesub', 'Welcome back');

        // KPI gradient tiles: four, each a GSAP card carrying its label in .lbl.
        $kpiLabels = $crawler
            ->filter('[aria-labelledby="kpi-heading"] .lr-kpi[data-gsap="card"] .lbl')
            ->each(static fn ($n): string => trim($n->text()));
        self::assertSame(
            ["Today's Revenue", 'Active Memberships', 'Upcoming Reservations', 'Open Refund Requests'],
            $kpiLabels,
        );

        // Recent Activity feed carries every mock transaction row.
        $activityRows = $crawler->filter('[aria-labelledby="activity-heading"] .lr-list-row')->count();
        self::assertGreaterThanOrEqual(10, $activityRows);

        // Stubbed presentation sections render.
        $eventRows = $crawler->filter('[aria-labelledby="events-heading"] .lr-list-row')->count();
        self::assertGreaterThanOrEqual(3, $eventRows);
        $facilityRows = $crawler->filter('[aria-labelledby="facilities-heading"] .lr-list-row')->count();
        self::assertGreaterThanOrEqual(3, $facilityRows);

        // Quick Actions: the seven nav routes as dashed tiles.
        $quickActions = $crawler->filter('[aria-labelledby="quick-actions-heading"] a.lr-quick')->count();
        self::assertSame(7, $quickActions);
    }
}
