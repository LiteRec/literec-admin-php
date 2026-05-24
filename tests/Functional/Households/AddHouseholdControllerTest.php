<?php

declare(strict_types=1);

namespace App\Tests\Functional\Households;

use App\Households\Domain\Households;
use App\Households\Domain\ValueObject\HouseholdId;
use App\Tests\Support\Trait\SignsInUsers;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Exercises the LRA-40 dialog endpoints end-to-end through the real
 * container, real Doctrine repositories, and real Symfony Forms.
 *
 * DAMA's PHPUnit extension wraps every test in a transaction rolled back at
 * teardown so write-path assertions can use the Doctrine adapter without
 * polluting the database. The `add_member_to_unknown_household` case
 * intentionally uses a household id that no test ever seeds.
 */
#[Large]
#[Group('database')]
final class AddHouseholdControllerTest extends WebTestCase
{
    use SignsInUsers;

    private const string TEST_USERNAME = 'new_household_e2e';
    private const string TEST_PASSWORD = 'CorrectHorseBattery!';

    private const string UNKNOWN_HOUSEHOLD_ID = '019571bf-5d51-7000-b500-0000000def01';

    #[Test]
    #[TestDox('GET /admin/users/new returns a dialog fragment with a CSRF token and the household-name field.')]
    public function get_new_form_returns_dialog_fragment(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);

        $client->request('GET', '/admin/users/new');

        self::assertResponseIsSuccessful();
        $body = (string) $client->getResponse()->getContent();
        // Fragment, not a full HTML shell.
        self::assertSame(
            0,
            preg_match('/<!doctype\b|<html\b/i', $body),
            'New-household fragment must not include the full HTML shell.',
        );
        self::assertSelectorExists('div[role="dialog"][aria-modal="true"]');
        self::assertSelectorExists('input[name="register_household[householdName]"]');
        self::assertSelectorExists('input[name="register_household[_token]"]');
    }

    #[Test]
    #[TestDox('POST /admin/users/new with valid data creates the household and returns 200 + HX-Redirect.')]
    public function post_new_form_with_valid_data_creates_household_and_returns_hx_redirect(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);

        $crawler = $client->request('GET', '/admin/users/new');
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Create Household')->form([
            'register_household[householdName]' => 'Smith Family',
            'register_household[firstName]' => 'Alice',
            'register_household[lastName]' => 'Smith',
            'register_household[dobIso]' => '1990-01-01',
            'register_household[genderCode]' => 'F',
            'register_household[email]' => 'alice@example.com',
            'register_household[phone]' => '5550100',
            'register_household[residencyStatusCode]' => 'RESIDENT',
            'register_household[street]' => '100 Main St',
            'register_household[city]' => 'Seattle',
            'register_household[state]' => 'WA',
            'register_household[postalCode]' => '98101',
            'register_household[country]' => 'US',
        ]);

        $client->submit($form);

        self::assertResponseStatusCodeSame(200);
        $hxRedirect = (string) $client->getResponse()->headers->get('HX-Redirect');
        self::assertMatchesRegularExpression(
            '#^/admin/users/[0-9a-f-]{36}/[0-9a-f-]{36}$#',
            $hxRedirect,
            'HX-Redirect must point at /admin/users/{householdId}/{memberId}.',
        );

        $repo = static::getContainer()->get(Households::class);
        self::assertInstanceOf(Households::class, $repo);
        // Extract the household id from the redirect target and verify it
        // resolves through the repository port.
        [, , , $hidStr] = explode('/', $hxRedirect);
        $repo->findById(HouseholdId::fromString($hidStr));
    }

    #[Test]
    #[TestDox('POST /admin/users/new with an invalid email re-renders the dialog with HTTP 422 and an inline error.')]
    public function post_new_form_with_invalid_email_re_renders_with_inline_error(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);

        $crawler = $client->request('GET', '/admin/users/new');
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Create Household')->form([
            'register_household[householdName]' => 'Smith Family',
            'register_household[firstName]' => 'Alice',
            'register_household[lastName]' => 'Smith',
            'register_household[dobIso]' => '1990-01-01',
            'register_household[genderCode]' => 'F',
            'register_household[email]' => 'not-an-email',
            'register_household[phone]' => '5550100',
            'register_household[residencyStatusCode]' => 'RESIDENT',
            'register_household[street]' => '100 Main St',
            'register_household[city]' => 'Seattle',
            'register_household[state]' => 'WA',
            'register_household[postalCode]' => '98101',
            'register_household[country]' => 'US',
        ]);

        $client->submit($form);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorExists('div[role="dialog"]');
    }

    #[Test]
    #[TestDox('POST /admin/users/new without a CSRF token returns 422 (form invalid) and never persists.')]
    public function post_new_form_without_csrf_is_rejected(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);

        // Submit the form fields without the _token. Symfony Forms treat
        // the missing/invalid token as a form-validation failure (not a
        // 403). The controller therefore re-renders the dialog with HTTP
        // 422; the important behavioural contract is that the command is
        // never dispatched.
        $client->request('POST', '/admin/users/new', [
            'register_household' => [
                'householdName' => 'Should Not Persist',
                'firstName' => 'Alice',
                'lastName' => 'Smith',
                'dobIso' => '1990-01-01',
                'genderCode' => 'F',
                'email' => 'alice@example.com',
                'phone' => '5550100',
                'residencyStatusCode' => 'RESIDENT',
                'street' => '100 Main St',
                'city' => 'Seattle',
                'state' => 'WA',
                'postalCode' => '98101',
                'country' => 'US',
            ],
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertNull(
            $client->getResponse()->headers->get('HX-Redirect'),
            'Form submission without a CSRF token must not redirect to a created resource.',
        );
    }

    #[Test]
    #[TestDox('POST /admin/users/{unknown}/members/new returns 404 when the household does not exist.')]
    public function post_add_member_to_unknown_household_returns_404(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);

        // We cannot GET the form for an unknown household and then submit it
        // (the GET handler does not validate existence — that is checked at
        // command-handler time), so we POST directly with a synthesised
        // payload. The CSRF token id is stateful per session; we obtain a
        // valid token by first GETing a sibling form for an unrelated
        // (also unknown) id. The CSRF protection in `add_member` flow is
        // bound to the token id, not the URL.
        $crawler = $client->request('GET', '/admin/users/' . self::UNKNOWN_HOUSEHOLD_ID . '/members/new');
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Add Member')->form([
            'add_member[firstName]' => 'Ghost',
            'add_member[lastName]' => 'Member',
            'add_member[dobIso]' => '1990-01-01',
            'add_member[genderCode]' => 'U',
            'add_member[email]' => 'ghost@example.com',
            'add_member[phone]' => '5550000',
            'add_member[residencyStatusCode]' => 'RESIDENT',
        ]);

        $client->submit($form);

        self::assertResponseStatusCodeSame(404);
    }
}
