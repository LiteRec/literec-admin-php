<?php

declare(strict_types=1);

namespace App\Tests\Support\Trait;

use App\Users\Application\Command\IssueOneTimePassword;
use App\Users\Application\Command\RegisterUser;
use App\Users\Domain\User;
use App\Users\Domain\Users;
use App\Users\Domain\ValueObject\OneTimePassword;
use App\Users\Domain\ValueObject\Username;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Functional-test helper for the LRA-213 one-time-password login flow:
 * registering a user, issuing (or re-issuing) a one-time password for
 * them, and signing in via the real login form. Split into separate
 * register/issue steps — rather than one combined helper — so a test can
 * issue a second, fresh credential for the same user without registering
 * them twice.
 */
trait IssuesOneTimePasswords
{
    private function registerUser(string $username): void
    {
        $bus = static::getContainer()->get(MessageBusInterface::class);
        self::assertInstanceOf(MessageBusInterface::class, $bus);

        $bus->dispatch(new RegisterUser($username, 'CorrectHorseBattery!')); // NOSONAR test fixture
    }

    private function issueOtpFor(string $username): OneTimePassword
    {
        $bus = static::getContainer()->get(MessageBusInterface::class);
        self::assertInstanceOf(MessageBusInterface::class, $bus);

        $users = static::getContainer()->get(Users::class);
        self::assertInstanceOf(Users::class, $users);
        $user = $users->byUsername(Username::of($username));
        self::assertInstanceOf(User::class, $user);

        $envelope = $bus->dispatch(new IssueOneTimePassword($user->id()->value));
        $otp = $envelope->last(HandledStamp::class)?->getResult();
        self::assertInstanceOf(OneTimePassword::class, $otp);

        return $otp;
    }

    private function submitLogin(KernelBrowser $client, string $username, string $password): void
    {
        $crawler = $client->request('GET', '/login');
        $form = $crawler->selectButton('Login')->form([
            '_username' => $username,
            '_password' => $password,
        ]);
        $client->submit($form);
    }

    /**
     * The logout route has CSRF protection enabled (security.yaml), and
     * the sign-out form only renders inside the authenticated app shell —
     * not on the forced "set a new password" page a one-time-password
     * login lands on. Fetching the token directly from the container is
     * the only way to log out from there in a test.
     */
    private function logOut(KernelBrowser $client): void
    {
        $tokenManager = static::getContainer()->get(CsrfTokenManagerInterface::class);
        self::assertInstanceOf(CsrfTokenManagerInterface::class, $tokenManager);

        $client->request('GET', '/logout', ['_csrf_token' => $tokenManager->getToken('logout')->getValue()]);
    }
}
