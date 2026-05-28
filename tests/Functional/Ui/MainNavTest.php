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
final class MainNavTest extends WebTestCase
{
    use SignsInUsers;

    private const string TEST_USERNAME = 'mainnav_e2e';

    #[Test]
    #[TestDox('All seven top-level nav buttons render on the dashboard in the legacy order.')]
    public function it_renders_all_seven_top_level_buttons_on_the_dashboard(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);

        $client->request('GET', '/dashboard');
        self::assertResponseIsSuccessful();

        $expected = [
            'Cash Register',
            'Programs',
            'Users',
            'Memberships',
            'Facilities',
            'Reports',
            'Communications',
        ];

        $crawler = $client->getCrawler();
        $actual = $crawler
            ->filter('nav[aria-label="Main navigation"] [role="menubar"] > li > a[role="menuitem"]')
            ->each(static fn ($node): string => trim($node->text()));

        self::assertSame($expected, $actual);
    }

    #[Test]
    #[TestDox('A sub-item link resolves to the shared coming-soon stub.')]
    public function a_sub_item_route_renders_the_coming_soon_stub(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);

        // Inventory is now backed by a real controller (LRA-85); pick
        // another sub-item that is still served by the placeholder stub.
        $client->request('GET', '/cash-register/pos-transactions');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main h1', 'POS Transactions');
        self::assertSelectorTextContains('main h2', 'Coming soon');
    }

    #[Test]
    #[TestDox('Each top-level button visually distinguishes the active section.')]
    public function active_top_level_item_is_visually_distinguished(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);

        $client->request('GET', '/programs');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists(
            'nav[aria-label="Main navigation"] [role="menuitem"][href="/programs"].bg-litrec-primary',
        );
    }
}
