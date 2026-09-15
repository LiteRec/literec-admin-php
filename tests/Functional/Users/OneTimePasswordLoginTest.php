<?php

declare(strict_types=1);

namespace App\Tests\Functional\Users;

use App\Users\Application\Command\IssueOneTimePassword;
use App\Users\Application\Command\RegisterUser;
use App\Users\Domain\User;
use App\Users\Domain\Users;
use App\Users\Domain\ValueObject\OneTimePassword;
use App\Users\Domain\ValueObject\Username;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

/**
 * End-to-end coverage of the LRA-213 one-time-password login flow: forced
 * password change on first use, and rejection of a second attempt to sign
 * in with the same (already-consumed) credential.
 */
#[Large]
#[Group('database')]
final class OneTimePasswordLoginTest extends WebTestCase
{
    private const string USERNAME = 'otp_login_e2e';

    private const string NEW_PASSWORD = 'a-brand-new-password'; // NOSONAR test fixture

    private const string LOGIN_ROUTE = '/login';

    private const string ACCOUNT_PASSWORD_ROUTE = '/account/password';

    #[Test]
    #[TestDox('Signing in with a one-time password forces a password change before any other page loads.')]
    public function one_time_password_forces_a_password_change_then_works_exactly_once(): void
    {
        $client = static::createClient();
        $otp = $this->issueOtpFor(self::USERNAME);

        // 1. Signing in with the one-time password redirects to the forced
        //    "set a new password" page rather than the dashboard.
        $this->submitLogin($client, self::USERNAME, $otp->value);
        self::assertResponseRedirects(self::ACCOUNT_PASSWORD_ROUTE);
        $client->followRedirect();
        self::assertResponseIsSuccessful();

        // 2. Any other authenticated route redirects back to that page
        //    until the password is replaced.
        $client->request('GET', '/dashboard');
        self::assertResponseRedirects(self::ACCOUNT_PASSWORD_ROUTE);

        // 3. Setting a new password logs the user out and sends them back
        //    to /login with a success flash.
        $crawler = $client->request('GET', self::ACCOUNT_PASSWORD_ROUTE);
        $form = $crawler->selectButton('Set password')->form([
            'establish_password[newPassword][first]' => self::NEW_PASSWORD,
            'establish_password[newPassword][second]' => self::NEW_PASSWORD,
        ]);
        $client->submit($form);
        self::assertResponseRedirects(self::LOGIN_ROUTE);
        $crawler = $client->followRedirect();
        self::assertSelectorExists('p[role="alert"]');

        // 4. The new password now works and reaches the dashboard.
        $form = $crawler->selectButton('Login')->form([
            '_username' => self::USERNAME,
            '_password' => self::NEW_PASSWORD,
        ]);
        $client->submit($form);
        self::assertResponseRedirects('/dashboard');

        // 5. The original one-time password is now consumed: a second
        //    attempt to sign in with it is rejected outright. createClient()
        //    can only be called once per test, so reuse the same browser
        //    after logging out of the freshly-established session.
        $client->request('GET', '/logout');
        $this->submitLogin($client, self::USERNAME, $otp->value);
        self::assertResponseRedirects(self::LOGIN_ROUTE);
        $client->followRedirect();
        self::assertSelectorExists('p[role="alert"]');
    }

    private function issueOtpFor(string $username): OneTimePassword
    {
        $bus = static::getContainer()->get(MessageBusInterface::class);
        self::assertInstanceOf(MessageBusInterface::class, $bus);

        $bus->dispatch(new RegisterUser($username, 'CorrectHorseBattery!')); // NOSONAR test fixture

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
        $crawler = $client->request('GET', self::LOGIN_ROUTE);
        $form = $crawler->selectButton('Login')->form([
            '_username' => $username,
            '_password' => $password,
        ]);
        $client->submit($form);
    }
}
