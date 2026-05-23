<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Users\Infrastructure\Persistence\Doctrine\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

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
        $user = $this->seedUser('disabled_e2e', 'CorrectHorseBattery!');
        $user->setIsActive(false);

        $container = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $entityManager->flush();

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
        $entityManager = $container->get(EntityManagerInterface::class);
        $hasher = $container->get(UserPasswordHasherInterface::class);

        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        self::assertInstanceOf(UserPasswordHasherInterface::class, $hasher);

        $user = new User($username);
        $user->setPassword($hasher->hashPassword($user, $plainPassword));

        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }
}
