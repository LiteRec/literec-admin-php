<?php

declare(strict_types=1);

namespace App\Tests\Functional\Ui;

use App\Tests\Support\Trait\SignsInUsers;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Pins the presentation-only Cash Register screens (LRA-130): the Full register
 * three-pane layout with the shared mode toggle, and the Quick route the toggle
 * links to. Stubbed sample data; no backend mutation is exercised.
 */
#[Large]
#[Group('database')]
final class CashRegisterPageTest extends WebTestCase
{
    use SignsInUsers;

    private const string TEST_USERNAME = 'cash_register_e2e';

    #[Test]
    #[TestDox('The Full register renders the three panes, cart, totals, and the active Full mode toggle.')]
    public function full_register_renders_the_three_pane_layout(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);

        $client->request('GET', '/cash-register');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main h1', 'Cash Register');

        // Payer pane.
        self::assertSelectorTextContains('main', 'Mike Bocker');
        // Builder pane.
        self::assertSelectorExists('.lr-tabs .lr-tab.is-active');
        self::assertSelectorTextContains('main', 'Add to Cart');
        // Cart pane.
        self::assertSelectorTextContains('main', 'Shopping Cart');
        self::assertSelectorTextContains('main', 'Corporate 1-Year Membership');
        // Summary pane.
        self::assertSelectorTextContains('.lr-totals .row.total', '$653.00');
        self::assertSelectorTextContains('main', 'Complete Sale');

        // Mode toggle: Full is the current segment.
        self::assertSelectorTextContains('.lr-seg a[aria-current="page"]', 'Full register');
    }

    #[Test]
    #[TestDox('The Quick route is reachable and shows the mode toggle with Quick active.')]
    public function quick_route_renders_with_the_quick_mode_active(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);

        $client->request('GET', '/cash-register/quick');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main h1', 'Cash Register');
        self::assertSelectorTextContains('.lr-seg a[aria-current="page"]', 'Quick sale');
    }
}
