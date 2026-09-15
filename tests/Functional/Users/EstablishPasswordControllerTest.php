<?php

declare(strict_types=1);

namespace App\Tests\Functional\Users;

use App\Tests\Support\Trait\SignsInUsers;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Validation-error coverage for the "set a new password" page (LRA-213).
 * The happy-path forced-change flow is covered end-to-end by
 * {@see OneTimePasswordLoginTest}; this test targets the 422 branches for
 * an already-authenticated (Established) user reaching the page directly.
 */
#[Large]
#[Group('database')]
final class EstablishPasswordControllerTest extends WebTestCase
{
    use SignsInUsers;

    #[Test]
    #[TestDox('Mismatched password fields return 422 with an inline error.')]
    public function mismatched_passwords_return_422(): void
    {
        $client = static::createClient();
        $this->signInUser($client, 'establish_mismatch_e2e', self::TEST_PASSWORD);

        $crawler = $client->request('GET', '/account/password');
        $form = $crawler->selectButton('Set password')->form([
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
        $client = static::createClient();
        $this->signInUser($client, 'establish_short_e2e', self::TEST_PASSWORD);

        $crawler = $client->request('GET', '/account/password');
        $form = $crawler->selectButton('Set password')->form([
            'establish_password[newPassword][first]' => 'short',
            'establish_password[newPassword][second]' => 'short',
        ]);
        $client->submit($form);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorExists('[data-testid="establish-password-form-error"]');
    }
}
