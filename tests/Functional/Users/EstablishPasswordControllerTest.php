<?php

declare(strict_types=1);

namespace App\Tests\Functional\Users;

use App\Tests\Support\Trait\IssuesOneTimePasswords;
use App\Tests\Support\Trait\SignsInUsers;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Form;

/**
 * Validation-error coverage for the "set a new password" page (LRA-213).
 * The happy-path forced-change flow is covered end-to-end by
 * {@see OneTimePasswordLoginTest}; this test targets the 422 branches.
 *
 * Reaches the page via a one-time-password login rather than an ordinary
 * (Established) sign-in: the controller redirects Established accounts
 * away (LRA-213 review round — see EstablishPasswordController), so only
 * an account whose password must still be replaced can exercise these
 * branches at all.
 */
#[Large]
#[Group('database')]
final class EstablishPasswordControllerTest extends WebTestCase
{
    use IssuesOneTimePasswords;
    use SignsInUsers;

    #[Test]
    #[TestDox('An Established account is redirected to the dashboard instead of seeing the form.')]
    public function established_account_is_redirected_to_the_dashboard(): void
    {
        $client = static::createClient();
        $this->signInUser($client, 'establish_gate_e2e', self::TEST_PASSWORD);

        $client->request('GET', '/account/password');

        self::assertResponseRedirects('/dashboard');
    }

    #[Test]
    #[TestDox('Mismatched password fields return 422 with an inline error.')]
    public function mismatched_passwords_return_422(): void
    {
        [$client, $form] = $this->establishPasswordForm('establish_mismatch_e2e', [
            'establish_password[newPassword][first]' => 'a-new-password-1',
            'establish_password[newPassword][second]' => 'a-different-password',
        ]);
        $client->submit($form);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorExists('[data-testid="establish-password-form-error"]');
    }

    #[Test]
    #[TestDox('A too-short password returns 422 with an inline error.')]
    public function too_short_password_returns_422(): void
    {
        [$client, $form] = $this->establishPasswordForm('establish_short_e2e', [
            'establish_password[newPassword][first]' => 'short',
            'establish_password[newPassword][second]' => 'short',
        ]);
        $client->submit($form);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorExists('[data-testid="establish-password-form-error"]');
    }

    /**
     * Registers the given username, issues it a one-time password, signs
     * in with it — the forced-flow redirect lands on /account/password
     * already — and fills the "set password" form with the given values.
     *
     * @param array<string, string> $values
     *
     * @return array{0: KernelBrowser, 1: Form}
     */
    private function establishPasswordForm(string $username, array $values): array
    {
        $client = static::createClient();
        $this->registerUser($username);
        $otp = $this->issueOtpFor($username);

        $this->submitLogin($client, $username, $otp->value);
        self::assertResponseRedirects('/account/password');
        $crawler = $client->followRedirect();

        return [$client, $crawler->selectButton('Set password')->form($values)];
    }
}
