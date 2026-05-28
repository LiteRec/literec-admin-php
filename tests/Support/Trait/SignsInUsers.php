<?php

declare(strict_types=1);

namespace App\Tests\Support\Trait;

use App\Users\Application\Command\RegisterUser;
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
        $container = static::getContainer();
        $bus = $container->get(MessageBusInterface::class);
        $bus->dispatch(new RegisterUser($username, $password));

        $crawler = $client->request('GET', '/login');
        $form = $crawler->selectButton('Login')->form([
            '_username' => $username,
            '_password' => $password,
        ]);
        $client->submit($form);
        $client->followRedirect();
        self::assertResponseIsSuccessful();
    }
}
