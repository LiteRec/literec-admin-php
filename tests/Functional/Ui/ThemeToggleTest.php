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
 * Pins the light/dark theme toggle (LRA-123): the no-flash bootstrap script is
 * present on every page, and the header exposes a keyboard-operable toggle
 * control with an accessible name. The persistence behaviour itself is
 * browser-JS (localStorage + Alpine) and out of WebTestCase reach, so this
 * suite asserts the static contract the JS hangs off, not the runtime toggle.
 */
#[Large]
#[Group('database')]
final class ThemeToggleTest extends WebTestCase
{
    use SignsInUsers;

    private const string TEST_USERNAME = 'theme_toggle_e2e';

    #[Test]
    #[TestDox('The no-flash theme bootstrap script is emitted in the head before paint.')]
    public function it_emits_the_no_flash_bootstrap_script(): void
    {
        $client = static::createClient();

        // The script lives in base.html.twig, so it is present even on the
        // public login page with no authenticated shell.
        $client->request('GET', '/login');

        self::assertResponseIsSuccessful();
        $html = (string) $client->getResponse()->getContent();
        self::assertStringContainsString("localStorage.getItem('lr-theme')", $html);
        self::assertStringContainsString("setAttribute('data-theme'", $html);
        self::assertStringContainsString("prefers-color-scheme: dark", $html);
    }

    #[Test]
    #[TestDox('The header exposes a keyboard-operable theme toggle with an accessible name.')]
    public function it_renders_an_accessible_theme_toggle_in_the_header(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);

        $client->request('GET', '/dashboard');

        self::assertResponseIsSuccessful();
        // A real <button> in the header (keyboard-operable) with an accessible
        // name and the JS hook that drives the toggle.
        self::assertSelectorExists('header button[data-testid="theme-toggle"]');
        self::assertSelectorExists('header button[data-testid="theme-toggle"][aria-label="Toggle dark theme"]');

        // The Alpine @click hook drives persistence. Assert it on the raw
        // response: Symfony's HTML5 crawler drops @-prefixed attributes, so it
        // is invisible to filter()/outerHtml() even though browsers honour it.
        self::assertStringContainsString(
            "localStorage.setItem('lr-theme'",
            (string) $client->getResponse()->getContent(),
        );
    }
}
