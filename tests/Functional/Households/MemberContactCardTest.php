<?php

declare(strict_types=1);

namespace App\Tests\Functional\Households;

use App\Households\Domain\Household;
use App\Households\Domain\HouseholdMember;
use App\Households\Domain\Households;
use App\Households\Domain\ValueObject\Address;
use App\Households\Domain\ValueObject\DateOfBirth;
use App\Households\Domain\ValueObject\Gender;
use App\Households\Domain\ValueObject\HouseholdId;
use App\Households\Domain\ValueObject\HouseholdName;
use App\Households\Domain\ValueObject\MemberCode;
use App\Households\Domain\ValueObject\MemberId;
use App\Households\Domain\ValueObject\PersonName;
use App\Households\Domain\ValueObject\ResidencyStatus;
use App\Shared\Domain\ValueObject\EmailAddress;
use App\Tests\Support\Trait\SignsInUsers;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\MockClock;

/**
 * Drives the Contact sub-card (LRA-204) read/edit flow end-to-end through
 * the real container, the Doctrine read model, the command bus, and a
 * freshly-seeded Households aggregate. DAMA rolls back the seeded rows at
 * teardown so cases stay isolated.
 */
#[Large]
#[Group('database')]
final class MemberContactCardTest extends WebTestCase
{
    use SignsInUsers;

    /** Reused literals (SonarCloud php:S1192). */
    private const string DOB = '1990-01-01';
    private const string ROUTE_CONTACT = '/admin/users/%s/%s/contact';
    private const string SEEDED_EMAIL = 'alice@example.com';
    private const string SELECTOR_PROFILE_EMAIL = '[data-testid="profile-email"]';
    private const string SELECTOR_PROFILE_PHONE = '[data-testid="profile-phone"]';
    private const string NEW_EMAIL = 'alicia.new@example.com';
    private const string NEW_PHONE = '5559999';

    private const string TEST_USERNAME = 'contact_card_e2e';

    private const string HOUSEHOLD_A    = '019571bf-5d55-7000-b500-00000000dd01';
    private const string A_PRIMARY_ID   = '019571bf-5d55-7000-b500-00000000dd02';
    private const string A_PRIMARY_CODE = 'M000420';

    private const string UNKNOWN_MEMBER_ID = '019571bf-5d55-7000-b500-0000000000fe';

    private MockClock $clock;

    protected function setUp(): void
    {
        $this->clock = new MockClock(new DateTimeImmutable('2026-05-24 12:00:00'));
    }

    #[Test]
    #[TestDox('GET member detail renders the Contact sub-card with the seeded email and a "—" phone.')]
    public function get_member_detail_renders_contact_sub_card_with_seeded_values(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);
        $this->seedHouseholdA();

        $client->request('GET', sprintf('/admin/users/%s/%s', self::HOUSEHOLD_A, self::A_PRIMARY_ID));

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-testid="contact-sub-card-body"]');
        self::assertSelectorTextContains(self::SELECTOR_PROFILE_EMAIL, self::SEEDED_EMAIL);
        self::assertSelectorTextContains(self::SELECTOR_PROFILE_PHONE, '—');
    }

    #[Test]
    #[TestDox('GET /contact/edit returns the edit partial pre-populated with the current email.')]
    public function get_contact_edit_returns_edit_partial_pre_populated(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);
        $this->seedHouseholdA();

        $crawler = $client->request(
            'GET',
            sprintf('/admin/users/%s/%s/contact/edit', self::HOUSEHOLD_A, self::A_PRIMARY_ID),
        );

        self::assertResponseIsSuccessful();

        // Fragment, not a full page.
        $body = (string) $client->getResponse()->getContent();
        self::assertStringNotContainsStringIgnoringCase('<!doctype', $body);
        self::assertStringNotContainsStringIgnoringCase('<html', $body);

        self::assertSelectorExists('#contact-sub-card-body form');
        self::assertSame(
            self::SEEDED_EMAIL,
            $crawler->filter('input[name="update_member_contact[email]"]')->attr('value'),
        );

        // The error summary region is persistent (LRA-158, WCAG 4.1.3): present
        // even with no errors, but sr-only and empty so it stays silent until a
        // 422 fills it.
        $errorRegion = $crawler->filter('[data-testid="contact-form-error"]');
        self::assertCount(1, $errorRegion, 'Expected exactly one persistent error region.');
        self::assertStringContainsString('sr-only', (string) $errorRegion->attr('class'));
        self::assertSame('', trim($errorRegion->text('')));
    }

    #[Test]
    #[TestDox('POST with valid email and phone swaps back to read mode, persists, and sets contactSaved HX-Trigger.')]
    public function post_contact_with_valid_changes_swaps_back_to_read_mode_and_persists(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);
        $this->seedHouseholdA();

        $this->postContactUpdate($client, self::HOUSEHOLD_A, self::A_PRIMARY_ID, [
            'email' => self::NEW_EMAIL,
            'phone' => self::NEW_PHONE,
        ]);

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-testid="contact-sub-card-body"]');
        self::assertSelectorTextContains(self::SELECTOR_PROFILE_EMAIL, self::NEW_EMAIL);
        self::assertSelectorTextContains(self::SELECTOR_PROFILE_PHONE, self::NEW_PHONE);
        self::assertSame('contactSaved', $client->getResponse()->headers->get('HX-Trigger'));

        $member = $this->firstMember(self::HOUSEHOLD_A);
        self::assertSame(self::NEW_EMAIL, (string) $member->email());
        self::assertSame(self::NEW_PHONE, (string) $member->phone());
    }

    #[Test]
    #[TestDox('POST with both fields empty clears both channels: renders "—" and the aggregate holds null.')]
    public function post_contact_with_both_fields_empty_clears_both_channels(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);
        $this->seedHouseholdA();

        $this->postContactUpdate($client, self::HOUSEHOLD_A, self::A_PRIMARY_ID, [
            'email' => '',
            'phone' => '',
        ]);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains(self::SELECTOR_PROFILE_EMAIL, '—');
        self::assertSelectorTextContains(self::SELECTOR_PROFILE_PHONE, '—');

        $member = $this->firstMember(self::HOUSEHOLD_A);
        self::assertNull($member->email());
        self::assertNull($member->phone());
    }

    #[Test]
    #[TestDox('POST with a malformed email re-renders the edit partial at 422 with an inline email error.')]
    public function post_contact_with_malformed_email_re_renders_edit_with_inline_error(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);
        $this->seedHouseholdA();

        $this->postContactUpdate($client, self::HOUSEHOLD_A, self::A_PRIMARY_ID, [
            'email' => 'not-an-email',
            'phone' => '',
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertNull(
            $client->getResponse()->headers->get('HX-Trigger'),
            'A 422 contact POST must not emit the contactSaved trigger.',
        );
        self::assertSelectorExists('#contact-sub-card-body form');
        $body = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('valid email address', $body);

        $member = $this->firstMember(self::HOUSEHOLD_A);
        self::assertSame(self::SEEDED_EMAIL, (string) $member->email());
    }

    #[Test]
    #[TestDox('POST with letters in the phone re-renders the edit partial at 422 with an inline phone error.')]
    public function post_contact_with_illegal_phone_characters_re_renders_edit_with_inline_error(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);
        $this->seedHouseholdA();

        $this->postContactUpdate($client, self::HOUSEHOLD_A, self::A_PRIMARY_ID, [
            'email' => '',
            'phone' => 'call-me-maybe',
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertNull(
            $client->getResponse()->headers->get('HX-Trigger'),
            'A 422 contact POST must not emit the contactSaved trigger.',
        );
        $body = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('digits and a leading', $body);

        $member = $this->firstMember(self::HOUSEHOLD_A);
        self::assertNull($member->phone());
    }

    #[Test]
    #[TestDox('POST without a CSRF token returns 422 and leaves the aggregate unchanged.')]
    public function post_contact_without_csrf_returns_422_form_invalid(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);
        $this->seedHouseholdA();

        $client->request(
            'POST',
            sprintf(self::ROUTE_CONTACT, self::HOUSEHOLD_A, self::A_PRIMARY_ID),
            [
                'update_member_contact' => [
                    'email' => 'mallory@example.com',
                    'phone' => '',
                    // No _token.
                ],
            ],
        );

        self::assertResponseStatusCodeSame(422);
        self::assertNull(
            $client->getResponse()->headers->get('HX-Trigger'),
            'A CSRF-rejected contact POST must not emit the contactSaved trigger.',
        );

        $member = $this->firstMember(self::HOUSEHOLD_A);
        self::assertSame(self::SEEDED_EMAIL, (string) $member->email());
    }

    #[Test]
    #[TestDox('POST for an unknown member id returns 404.')]
    public function post_contact_for_unknown_member_returns_404(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);
        $this->seedHouseholdA();

        // Get a valid CSRF token from the real edit form for the seeded
        // member, then POST it against an unknown member id. The CSRF
        // token is form-level (intent-based) and stays valid across the
        // session, so the request gets past CSRF and into the command
        // handler where MemberNotFound triggers the 404.
        $token = $this->csrfTokenFromEditForm($client);

        $client->request(
            'POST',
            sprintf(self::ROUTE_CONTACT, self::HOUSEHOLD_A, self::UNKNOWN_MEMBER_ID),
            [
                'update_member_contact' => [
                    'email' => 'ghost@example.com',
                    'phone' => '',
                    '_token' => $token,
                ],
            ],
        );

        self::assertResponseStatusCodeSame(404);
        self::assertNull(
            $client->getResponse()->headers->get('HX-Trigger'),
            'A 404 contact POST must not emit the contactSaved trigger.',
        );
    }

    /**
     * POSTs a contact update for the given member with a valid CSRF token
     * pulled from the real edit form. Callers assert on the resulting
     * response.
     *
     * @param array<string, string> $contactData
     */
    private function postContactUpdate(
        KernelBrowser $client,
        string $householdId,
        string $memberId,
        array $contactData,
    ): void {
        $contactData['_token'] = $this->csrfTokenFromEditForm($client);

        $client->request(
            'POST',
            sprintf(self::ROUTE_CONTACT, $householdId, $memberId),
            ['update_member_contact' => $contactData],
        );
    }

    private function csrfTokenFromEditForm(KernelBrowser $client): string
    {
        $crawler = $client->request(
            'GET',
            sprintf('/admin/users/%s/%s/contact/edit', self::HOUSEHOLD_A, self::A_PRIMARY_ID),
        );
        self::assertResponseIsSuccessful();

        $tokenField = $crawler->filter('input[name="update_member_contact[_token]"]');
        self::assertGreaterThan(0, $tokenField->count(), 'CSRF token field was not rendered in the edit form.');

        return (string) $tokenField->attr('value');
    }

    private function firstMember(string $householdId): HouseholdMember
    {
        $repo = static::getContainer()->get(Households::class);
        self::assertInstanceOf(Households::class, $repo);
        $household = $repo->findById(HouseholdId::fromString($householdId));

        foreach ($household->members() as $member) {
            return $member;
        }
        self::fail('Seeded household has no members.');
    }

    private function seedHouseholdA(): void
    {
        $repo = static::getContainer()->get(Households::class);
        self::assertInstanceOf(Households::class, $repo);

        $household = Household::register(
            HouseholdId::fromString(self::HOUSEHOLD_A),
            HouseholdName::of('Smith Family'),
            Address::of('100 Main St', 'Apt 2B', 'Seattle', 'WA', '98101', 'US'),
            MemberId::fromString(self::A_PRIMARY_ID),
            MemberCode::of(self::A_PRIMARY_CODE),
            PersonName::of('Alice', 'Smith'),
            DateOfBirth::of(new DateTimeImmutable(self::DOB), $this->clock),
            Gender::Female,
            EmailAddress::of(self::SEEDED_EMAIL),
            null,
            ResidencyStatus::Resident,
            $this->clock,
        );

        $repo->save($household);
    }
}
