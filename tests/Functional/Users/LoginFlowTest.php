<?php

declare(strict_types=1);

namespace App\Tests\Functional\Users;

use App\Users\Application\Command\RegisterUser;
use App\Users\Domain\User;
use App\Users\Domain\Users;
use App\Users\Domain\ValueObject\Username;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * End-to-end coverage of the login flow against a real database.
 * dama/doctrine-test-bundle wraps each test in a transaction that is
 * rolled back at the end, so the tests are isolated.
 */
#[Large]
#[Group('database')]
#[Group('smoke')]
final class LoginFlowTest extends WebTestCase
{
    /** Reused literals (SonarCloud php:S1192). */
    private const string ROUTE_LOGIN = '/login';

    // Test fixture, not a real credential.
    private const string TEST_PASSWORD = 'CorrectHorseBattery!'; // NOSONAR

    #[Test]
    #[TestDox('A seeded user can sign in with valid credentials and lands on /dashboard.')]
    public function seeded_user_can_log_in_and_reach_the_dashboard(): void
    {
        $client = static::createClient();
        $this->seedUser('alice_e2e', self::TEST_PASSWORD);

        $crawler = $client->request('GET', self::ROUTE_LOGIN);
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Login')->form([
            '_username' => 'alice_e2e',
            '_password' => self::TEST_PASSWORD,
        ]);
        $client->submit($form);

        self::assertResponseRedirects('/dashboard');
        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('strong', 'alice_e2e');
    }

    #[Test]
    #[TestDox('Wrong password keeps the user on /login with a visible error alert.')]
    public function bad_credentials_stay_on_login_with_an_error(): void
    {
        $client = static::createClient();
        $this->seedUser('bob_e2e', self::TEST_PASSWORD);

        $crawler = $client->request('GET', self::ROUTE_LOGIN);
        $form = $crawler->selectButton('Login')->form([
            '_username' => 'bob_e2e',
            '_password' => 'wrong',
        ]);
        $client->submit($form);

        self::assertResponseRedirects(self::ROUTE_LOGIN);
        $client->followRedirect();
        self::assertSelectorExists('p[role="alert"]');
    }

    #[Test]
    #[TestDox('An inactive user is rejected by UserChecker before the dashboard loads.')]
    public function inactive_user_is_rejected_by_user_checker(): void
    {
        $client = static::createClient();
        $this->seedUser('disabled_e2e', self::TEST_PASSWORD);

        $container = static::getContainer();
        $users = $container->get(Users::class);
        $clock = $container->get(ClockInterface::class);

        $user = $users->byUsername(Username::of('disabled_e2e'));
        $user->deactivate('e2e-disabled', $clock);
        $users->save($user);

        $crawler = $client->request('GET', self::ROUTE_LOGIN);
        $form = $crawler->selectButton('Login')->form([
            '_username' => 'disabled_e2e',
            '_password' => self::TEST_PASSWORD,
        ]);
        $client->submit($form);

        self::assertResponseRedirects(self::ROUTE_LOGIN);
        $client->followRedirect();
        self::assertSelectorExists('p[role="alert"]');
    }

    private function seedUser(string $username, string $plainPassword): User
    {
        $container = static::getContainer();
        $bus = $container->get(MessageBusInterface::class);
        $users = $container->get(Users::class);

        $bus->dispatch(new RegisterUser($username, $plainPassword));

        return $users->byUsername(Username::of($username));
    }
}
