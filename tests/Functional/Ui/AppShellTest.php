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
 * Pins the contract between app.html.twig and the AppShellExtension.
 *
 * The asserted strings ("Main Facility", "build dev") come from
 * config/services.yaml parameters (app.selected_facility_mock,
 * app.build_version with default "dev"). Tests will fail if either
 * parameter is removed or renamed — by design, so the shell can't
 * silently lose its facility indicator or build-version stamp.
 */
#[Large]
#[Group('database')]
final class AppShellTest extends WebTestCase
{
    use SignsInUsers;

    private const string TEST_USERNAME = 'shell_e2e';
    private const string TEST_PASSWORD = 'CorrectHorseBattery!';

    #[Test]
    #[TestDox('The dashboard renders inside the authenticated app shell: header, nav, content, footer.')]
    public function dashboard_renders_inside_the_app_shell(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);

        $client->request('GET', '/dashboard');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('header a', 'LiteRecAdmin');
        self::assertSelectorTextContains('header [data-testid="selected-facility"]', 'Main Facility');
        self::assertSelectorTextContains('header strong', self::TEST_USERNAME);
        self::assertSelectorExists('header form[action="/logout"] input[name="_csrf_token"]');
        self::assertSelectorExists('nav[aria-label="Main navigation"]');
        self::assertSelectorTextContains('main h1', 'Admin Dashboard');
        self::assertSelectorExists('main [data-gsap="card"]');
        self::assertSelectorTextContains('footer', 'LiteRec');
        self::assertSelectorTextContains('footer', 'build dev');
    }

    #[Test]
    #[TestDox('The public login page stays on base.html.twig and does not render the authenticated shell.')]
    public function login_page_does_not_render_the_app_shell(): void
    {
        $client = static::createClient();

        $client->request('GET', '/login');

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('header [data-testid="selected-facility"]');
        self::assertSelectorNotExists('nav[aria-label="Main navigation"]');
        self::assertSelectorNotExists('footer');
    }

}
