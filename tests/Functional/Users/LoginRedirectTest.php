<?php

declare(strict_types=1);

namespace App\Tests\Functional\Users;

use App\Tests\Support\Trait\SignsInUsers;
use App\Users\Application\Command\RegisterUser;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

/**
 * Pins the post-login redirect target so it cannot silently regress.
 *
 * The Symfony firewall's default_target_path must keep sending freshly-
 * authenticated staff to the Admin Dashboard route by name (app_dashboard).
 * This test fails if the firewall is reconfigured to a different route
 * name or to a hardcoded URL that drifts from the dashboard.
 */
#[Large]
#[Group('database')]
final class LoginRedirectTest extends WebTestCase
{
    use SignsInUsers;

    private const string TEST_USERNAME = 'login_redirect_e2e';

    #[Test]
    #[TestDox('Successful sign-in redirects to the Admin Dashboard route, by name.')]
    public function successful_sign_in_redirects_to_the_dashboard_route(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $bus = $container->get(MessageBusInterface::class);
        $router = $container->get('router');
        self::assertInstanceOf(RouterInterface::class, $router);

        $expectedTarget = $router->generate('app_dashboard', [], UrlGeneratorInterface::ABSOLUTE_PATH);

        $bus->dispatch(new RegisterUser(self::TEST_USERNAME, self::TEST_PASSWORD));

        $crawler = $client->request('GET', '/login');
        $form = $crawler->selectButton('Login')->form([
            '_username' => self::TEST_USERNAME,
            '_password' => self::TEST_PASSWORD,
        ]);
        $client->submit($form);

        self::assertResponseRedirects($expectedTarget);

        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main h1', 'Admin Dashboard');
    }
}
