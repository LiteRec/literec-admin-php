<?php

declare(strict_types=1);

namespace App\Tests\Functional\Ui;

use App\Tests\Support\Trait\SignsInUsers;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\Attributes\TestWith;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[Large]
#[Group('database')]
final class DashboardPageTest extends WebTestCase
{
    use SignsInUsers;

    private const string TEST_USERNAME = 'dashboard_e2e';

    #[Test]
    #[TestDox('Signed-in staff land on the redesigned Admin Dashboard with all Organic sections.')]
    public function dashboard_renders_the_organic_sections(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);

        $crawler = $client->request('GET', '/dashboard');
        self::assertResponseIsSuccessful();

        self::assertMatchesRegularExpression(
            '/^Good (morning|afternoon|evening), \w+$/',
            trim($crawler->filter('main h1')->text()),
        );
        self::assertSelectorTextContains('.lr-pagesub', (new DateTimeImmutable())->format('l, F j, Y'));
        self::assertSelectorTextContains('.lr-pagesub', 'Main Facility');

        // Page actions: Close-out report + New sale.
        self::assertSelectorTextContains('main a[href$="/reports"]', 'Close-out report');
        self::assertSelectorTextContains('main a[href="/cash-register"]', 'New sale');

        // Flat KPI cards: four, each carrying its label.
        $kpiLabels = $crawler
            ->filter('[aria-labelledby="kpi-heading"] [data-testid="kpi-card"] .lr-kpi-flat-label')
            ->each(static fn ($n): string => trim($n->text()));
        self::assertSame(
            ["Today's Revenue", 'Active Memberships', 'Upcoming Reservations', 'Open Refund Requests'],
            $kpiLabels,
        );
        self::assertSelectorNotExists('[aria-labelledby="kpi-heading"] .lr-kpi');

        // Recent transactions table carries every mock transaction row and a status filter.
        $transactionRows = $crawler
            ->filter('[aria-labelledby="transactions-heading"] [data-testid="transaction-row"]')
            ->count();
        self::assertGreaterThanOrEqual(10, $transactionRows);
        self::assertSelectorExists('[data-testid="transactions-filter-all"].is-active');
        self::assertSelectorTextContains('[data-testid="transactions-filter-label"]', 'Today');
        self::assertSame(
            'true',
            $crawler->filter('[data-testid="transactions-filter-all"]')->attr('aria-pressed'),
        );
        self::assertSame(
            'false',
            $crawler->filter('[data-testid="transactions-filter-pending"]')->attr('aria-pressed'),
        );

        // Stubbed presentation sections render.
        $eventRows = $crawler->filter('[aria-labelledby="upcoming-heading"] .lr-list-row')->count();
        self::assertGreaterThanOrEqual(3, $eventRows);
        $facilityRows = $crawler
            ->filter('[aria-labelledby="facilities-heading"] [data-testid="facility-row"]')
            ->count();
        self::assertGreaterThanOrEqual(3, $facilityRows);

        // Quick Actions is gone (LRA-189).
        self::assertSelectorNotExists('[aria-labelledby="quick-actions-heading"]');
    }

    #[Test]
    #[TestDox('GET /dashboard?status=pending renders the full page shell with the Pending pill pre-selected.')]
    public function dashboard_page_with_status_query_preselects_the_filter(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);

        $crawler = $client->request('GET', '/dashboard?status=pending');
        self::assertResponseIsSuccessful();

        // The full page shell rendered, not the bare HTMX table partial.
        self::assertSelectorExists('main h1');
        self::assertSelectorExists('[aria-labelledby="kpi-heading"]');

        self::assertSelectorTextContains('[data-testid="transactions-filter-label"]', 'Pending · today');
        self::assertSelectorExists('[data-testid="transactions-filter-pending"].is-active');
        self::assertSame(
            'true',
            $crawler->filter('[data-testid="transactions-filter-pending"]')->attr('aria-pressed'),
        );
        self::assertSame(
            'false',
            $crawler->filter('[data-testid="transactions-filter-all"]')->attr('aria-pressed'),
        );
    }

    #[Test]
    #[TestDox('The transactions partial filters by status and pushes the page URL, not its own.')]
    public function transactions_partial_filters_by_status(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);

        $crawler = $client->request('GET', '/dashboard/_transactions?status=pending');
        self::assertResponseIsSuccessful();

        self::assertSelectorTextContains('[data-testid="transactions-filter-label"]', 'Pending · today');
        self::assertSelectorExists('[data-testid="transactions-filter-pending"].is-active');

        // hx-push-url must be the dashboard page URL, not the partial's own
        // fetch URL — otherwise reloading/bookmarking lands on a bare,
        // unstyled table fragment (LRA-189 review).
        self::assertSame(
            '/dashboard?status=pending',
            $crawler->filter('[data-testid="transactions-filter-pending"]')->attr('hx-push-url'),
        );
        self::assertSame(
            '/dashboard',
            $crawler->filter('[data-testid="transactions-filter-all"]')->attr('hx-push-url'),
        );

        $badges = $crawler
            ->filter('[data-testid="transaction-row"] .lr-badge')
            ->each(static fn ($n): string => trim($n->text()));
        self::assertNotEmpty($badges);
        foreach ($badges as $badge) {
            self::assertSame('Pending', $badge);
        }
    }

    #[Test]
    #[TestWith(['bogus'], 'unknown status value falls back to All')]
    #[TestWith([''], 'empty status value falls back to All')]
    #[TestWith([null], 'missing status parameter falls back to All')]
    #[TestDox('An invalid, empty, or missing status query parameter silently shows All rather than erroring.')]
    public function transactions_partial_falls_back_to_all_for_invalid_status(?string $status): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);

        $url = '/dashboard/_transactions' . ($status !== null ? '?status=' . $status : '');
        $crawler = $client->request('GET', $url);
        self::assertResponseIsSuccessful();

        self::assertSelectorTextContains('[data-testid="transactions-filter-label"]', 'Today');
        self::assertSelectorExists('[data-testid="transactions-filter-all"].is-active');
        self::assertSame(
            'true',
            $crawler->filter('[data-testid="transactions-filter-all"]')->attr('aria-pressed'),
        );
        self::assertGreaterThanOrEqual(10, $crawler->filter('[data-testid="transaction-row"]')->count());
    }
}
