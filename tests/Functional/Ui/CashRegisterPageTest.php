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

        // Payer + Participant panes.
        self::assertSelectorTextContains('main', 'Mike Bocker');
        self::assertSelectorExists('.lr-pillradio input[type="radio"]:checked');
        self::assertSelectorTextContains('main', 'Add household member');
        // Builder pane.
        self::assertSelectorExists('.lr-tabs .lr-tab.is-active');
        self::assertSelectorExists('.lr-programrow.is-selected');
        self::assertSelectorTextContains('main', 'Advanced Tap Dancing');
        self::assertSelectorTextContains('main', 'Add to sale');
        self::assertSelectorExists('.lr-chip.is-selected[aria-pressed="true"]');
        // Sale rail.
        self::assertSelectorTextContains('[data-testid="sale-card-title"]', 'Sale');
        self::assertSelectorTextContains('main', 'Corporate 1-Year Membership');
        self::assertSelectorTextContains('.lr-totals .row.total', '$653.00');
        self::assertSelectorTextContains('main', 'Take payment');

        // Mode toggle: Full is the current segment.
        self::assertSelectorTextContains('.lr-seg a[aria-current="page"]', 'Full register');
    }

    #[Test]
    #[TestDox('The Quick route is reachable and shows the mode toggle with Quick active.')]
    public function quick_route_renders_with_the_quick_mode_active(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);

        $crawler = $client->request('GET', '/cash-register/quick');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main h1', 'Cash Register');
        self::assertSelectorTextContains('.lr-seg a[aria-current="page"]', 'Quick sale');

        // Item picker: scan/search field, Walk-in payer pill, category pills,
        // and a touch-tile grid of items. The first category pill ("All") is
        // pre-selected server-side, and every tender starts unselected, so a
        // screen reader (or the functional test, which never runs Alpine)
        // sees correct toggle state before any JS hydrates.
        self::assertSelectorExists('input[aria-label="Scan or search an item"]');
        self::assertSelectorTextContains('[data-testid="quick-sale-payer"]', 'Walk-in');
        self::assertSelectorTextContains('main', 'Day Passes');
        self::assertSelectorExists('[data-testid="quick-sale-category-0"][aria-pressed="true"].is-selected');
        self::assertGreaterThanOrEqual(8, $crawler->filter('.lr-tilegrid button.lr-tile')->count());
        self::assertSelectorTextContains('main', 'Adult Day Pass');

        // Receipt rail: line rows with steppers, totals, tender tiles, and a
        // charge action. The rendered totals — and the Adult Day Pass line's
        // own total (2 x $8.00) — are the QuickSaleData fallback, the same
        // numbers quickSale()'s initial Alpine state computes.
        self::assertSelectorTextContains('.lr-card-head', 'Receipt');
        self::assertSelectorExists('[data-testid="quick-sale-clear"]');
        self::assertSelectorExists('.lr-stepper');
        self::assertSelectorTextContains('[data-testid="receipt-line-adult-day-pass"]', '$16.00');
        self::assertSelectorTextContains('.lr-totals .row.total', '$25.68');
        self::assertSelectorTextContains('[data-testid="quick-sale-tender-cash"]', 'Cash');
        self::assertSelectorExists('[data-testid="quick-sale-tender-cash"][aria-pressed="false"]');
        self::assertSelectorTextContains('[data-testid="quick-sale-tender-card"]', 'Card');
        self::assertSelectorTextContains('[data-testid="quick-sale-tender-gift-card"]', 'Gift card');
        self::assertSelectorTextContains('main', 'Charge $25.68');
    }
}
