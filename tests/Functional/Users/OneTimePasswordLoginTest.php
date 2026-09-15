<?php

declare(strict_types=1);

namespace App\Tests\Functional\Users;

use App\Tests\Support\Trait\IssuesOneTimePasswords;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * End-to-end coverage of the LRA-213 one-time-password login flow: forced
 * password change on first use, and rejection of a second attempt to sign
 * in with the same (already-consumed) credential.
 */
#[Large]
#[Group('database')]
final class OneTimePasswordLoginTest extends WebTestCase
{
    use IssuesOneTimePasswords;

    private const string USERNAME = 'otp_login_e2e';

    private const string NEW_PASSWORD = 'a-brand-new-password'; // NOSONAR test fixture

    private const string LOGIN_ROUTE = '/login';

    private const string ACCOUNT_PASSWORD_ROUTE = '/account/password';

    #[Test]
    #[TestDox('Signing in with a one-time password forces a password change before any other page loads.')]
    public function one_time_password_forces_a_password_change_then_works_exactly_once(): void
    {
        $client = static::createClient();
        $this->registerUser(self::USERNAME);
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

        // 2b. The credential is now OneTimeConsumed (its hash is unchanged,
        //     so it still authenticates); re-using it is rejected by
        //     UserChecker with its own message, not a generic
        //     "Invalid credentials." — proving the single-use guard
        //     actually runs, not just that a stale hash eventually stops
        //     matching once establishPassword() replaces it.
        $this->logOut($client);
        $this->submitLogin($client, self::USERNAME, $otp->value);
        self::assertResponseRedirects(self::LOGIN_ROUTE);
        $client->followRedirect();
        self::assertSelectorTextContains('p[role="alert"]', 'already been used');

        // Re-enter the forced flow with a fresh credential to continue
        // through the "set a new password" steps below.
        $otp = $this->issueOtpFor(self::USERNAME);
        $this->submitLogin($client, self::USERNAME, $otp->value);
        self::assertResponseRedirects(self::ACCOUNT_PASSWORD_ROUTE);
        $client->followRedirect();

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

        // 4. The new password now works and reaches the dashboard. The
        //    one-time password itself is now doubly dead: its state is
        //    OneTimeConsumed (proven single-use above) *and* its hash has
        //    been replaced by establishPassword(), so it could never
        //    authenticate again even if the state check were removed.
        $form = $crawler->selectButton('Login')->form([
            '_username' => self::USERNAME,
            '_password' => self::NEW_PASSWORD,
        ]);
        $client->submit($form);
        self::assertResponseRedirects('/dashboard');
    }
}
