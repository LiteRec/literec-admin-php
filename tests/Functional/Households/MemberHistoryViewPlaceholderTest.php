<?php

declare(strict_types=1);

namespace App\Tests\Functional\Households;

use App\Tests\Support\Trait\SignsInUsers;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Drives the History card's placeholder route (LRA-206): every
 * "coming soon" kind (Activities, Memberships, Facility Rentals,
 * Equipment Rentals, POS Purchases) renders the shared placeholder
 * fragment from its own member-scoped URL. The route does no
 * existence lookup — it is only ever requested from an
 * already-resolved member detail page behind the admin firewall — so
 * the fixture ids below are UUID-v7-shaped but need not correspond to
 * a seeded household.
 */
#[Large]
#[Group('database')]
final class MemberHistoryViewPlaceholderTest extends WebTestCase
{
    use SignsInUsers;

    private const string TEST_USERNAME = 'history_placeholder_e2e';

    private const string HOUSEHOLD_ID = '019571bf-5d55-7000-b500-0000000000f1';
    private const string MEMBER_ID    = '019571bf-5d55-7000-b500-0000000000f2';

    /**
     * @return Generator<string, array{string}>
     */
    public static function comingSoonSlugs(): Generator
    {
        yield 'activities'        => ['activities'];
        yield 'memberships'       => ['memberships'];
        yield 'facility-rentals'  => ['facility-rentals'];
        yield 'equipment-rentals' => ['equipment-rentals'];
        yield 'pos-purchases'     => ['pos-purchases'];
    }

    #[Test]
    #[DataProvider('comingSoonSlugs')]
    #[TestDox('GET the placeholder route for a coming-soon kind renders its "Coming soon" panel: $_dataName.')]
    public function renders_coming_soon_panel_for_each_kind(string $slug): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);

        $client->request('GET', $this->historyViewUrl($slug));

        self::assertResponseIsSuccessful();
        self::assertSelectorExists(sprintf('[data-testid="history-view-placeholder-%s"]', $slug));
        self::assertSelectorTextContains(
            sprintf('[data-testid="history-view-placeholder-%s"]', $slug),
            'Coming soon',
        );
    }

    #[Test]
    #[TestDox('The Transactions slug 404s on this route — its real table stays on member_history_page.')]
    public function transactions_slug_is_not_found_on_this_route(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);

        $client->request('GET', $this->historyViewUrl('transactions'));

        self::assertResponseStatusCodeSame(404);
    }

    #[Test]
    #[TestDox('An unknown history-view slug 404s.')]
    public function unknown_slug_is_not_found(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);

        $client->request('GET', $this->historyViewUrl('not-a-real-view'));

        self::assertResponseStatusCodeSame(404);
    }

    #[Test]
    #[TestDox('A non-UUID-v7 member id 404s.')]
    public function malformed_member_id_is_not_found(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);

        $client->request(
            'GET',
            sprintf('/admin/users/%s/not-a-uuid/history/activities', self::HOUSEHOLD_ID),
        );

        self::assertResponseStatusCodeSame(404);
    }

    #[Test]
    #[TestDox('An anonymous request is redirected to the login page.')]
    public function anonymous_request_redirects_to_login(): void
    {
        $client = static::createClient();

        $client->request('GET', $this->historyViewUrl('activities'));

        self::assertResponseRedirects();
    }

    private function historyViewUrl(string $slug): string
    {
        return sprintf(
            '/admin/users/%s/%s/history/%s',
            self::HOUSEHOLD_ID,
            self::MEMBER_ID,
            $slug,
        );
    }
}
