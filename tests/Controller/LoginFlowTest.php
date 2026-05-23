<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Users\Application\Command\RegisterUser;
use App\Users\Domain\User;
use App\Users\Domain\Users;
use App\Users\Domain\ValueObject\Username;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * End-to-end coverage of the login flow against a real database.
 * dama/doctrine-test-bundle wraps each test in a transaction that is
 * rolled back at the end, so the tests are isolated.
 */
final class LoginFlowTest extends WebTestCase
{
    public function testSeededUserCanLogInAndReachTheDashboard(): void
    {
        $client = static::createClient();
        $this->seedUser('alice_e2e', 'CorrectHorseBattery!');

        $crawler = $client->request('GET', '/login');
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Login')->form([
            '_username' => 'alice_e2e',
            '_password' => 'CorrectHorseBattery!',
        ]);
        $client->submit($form);

        self::assertResponseRedirects('/dashboard');
        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('strong', 'alice_e2e');
    }

    public function testBadCredentialsStayOnLoginWithAnError(): void
    {
        $client = static::createClient();
        $this->seedUser('bob_e2e', 'CorrectHorseBattery!');

        $crawler = $client->request('GET', '/login');
        $form = $crawler->selectButton('Login')->form([
            '_username' => 'bob_e2e',
            '_password' => 'wrong',
        ]);
        $client->submit($form);

        self::assertResponseRedirects('/login');
        $client->followRedirect();
        self::assertSelectorExists('p[role="alert"]');
    }

    public function testInactiveUserIsRejectedByUserChecker(): void
    {
        $client = static::createClient();
        $this->seedUser('disabled_e2e', 'CorrectHorseBattery!');

        $container = static::getContainer();
        $users = $container->get(Users::class);
        $clock = $container->get(ClockInterface::class);

        $user = $users->byUsername(Username::of('disabled_e2e'));
        $user->deactivate('e2e-disabled', $clock);
        $users->save($user);

        $crawler = $client->request('GET', '/login');
        $form = $crawler->selectButton('Login')->form([
            '_username' => 'disabled_e2e',
            '_password' => 'CorrectHorseBattery!',
        ]);
        $client->submit($form);

        self::assertResponseRedirects('/login');
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
