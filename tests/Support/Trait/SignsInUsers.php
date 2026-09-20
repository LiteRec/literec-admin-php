<?php

declare(strict_types=1);

namespace App\Tests\Support\Trait;

use App\Shared\Infrastructure\Fixtures\HandledResult;
use App\Users\Application\Command\RegisterUser;
use App\Users\Domain\ValueObject\UserId;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Functional-test helper that registers a fresh user and signs them in
 * via the real login form. DAMA's transaction rollback isolates the
 * registration between tests, so callers can pick any username.
 */
trait SignsInUsers
{
    // Test fixture, not a real credential.
    private const string TEST_PASSWORD = 'CorrectHorseBattery!'; // NOSONAR

    private function signInUser(KernelBrowser $client, string $username, string $password): void
    {
        $this->registerAndLogIn($client, $username, $password);
    }

    /**
     * Same registration-and-login flow as {@see self::signInUser()}, but
     * returns the freshly registered {@see UserId} for callers that need
     * to chain a follow-up command against it — e.g.
     * {@see GrantsPrivileges}, which grants administrator status to the
     * account this creates.
     */
    private function registerAndLogIn(KernelBrowser $client, string $username, string $password): UserId
    {
        $container = static::getContainer();
        $bus = $container->get(MessageBusInterface::class);
        $userId = HandledResult::from($bus->dispatch(new RegisterUser($username, $password)), UserId::class);

        $crawler = $client->request('GET', '/login');
        $form = $crawler->selectButton('Login')->form([
            '_username' => $username,
            '_password' => $password,
        ]);
        $client->submit($form);
        $client->followRedirect();
        self::assertResponseIsSuccessful();

        return $userId;
    }
}
