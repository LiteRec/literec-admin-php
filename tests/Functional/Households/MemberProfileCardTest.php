<?php

declare(strict_types=1);

namespace App\Tests\Functional\Households;

use App\Households\Domain\Household;
use App\Households\Domain\Households;
use App\Households\Domain\ValueObject\Address;
use App\Households\Domain\ValueObject\DateOfBirth;
use App\Households\Domain\ValueObject\EmailAddress;
use App\Households\Domain\ValueObject\Gender;
use App\Households\Domain\ValueObject\HouseholdId;
use App\Households\Domain\ValueObject\HouseholdName;
use App\Households\Domain\ValueObject\MemberCode;
use App\Households\Domain\ValueObject\MemberId;
use App\Households\Domain\ValueObject\PersonName;
use App\Households\Domain\ValueObject\ResidencyStatus;
use App\Tests\Support\Trait\SignsInUsers;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\MockClock;

/**
 * Drives the Profile card (LRA-43) read/edit flow end-to-end through the
 * real container, the Doctrine read model, the command bus, and a
 * freshly-seeded Households aggregate. DAMA rolls back the seeded rows at
 * teardown so cases stay isolated.
 */
#[Large]
#[Group('database')]
final class MemberProfileCardTest extends WebTestCase
{
    use SignsInUsers;

    private const string TEST_USERNAME = 'profile_card_e2e';
    private const string TEST_PASSWORD = 'CorrectHorseBattery!';

    private const string HOUSEHOLD_A    = '019571bf-5d55-7000-b500-00000000aa01';
    private const string A_PRIMARY_ID   = '019571bf-5d55-7000-b500-00000000aa02';
    private const string A_PRIMARY_CODE = 'M000410';

    private const string UNKNOWN_MEMBER_ID = '019571bf-5d55-7000-b500-0000000000fe';

    private MockClock $clock;

    protected function setUp(): void
    {
        $this->clock = new MockClock(new DateTimeImmutable('2026-05-24 12:00:00'));
    }

    #[Test]
    #[TestDox('GET member detail renders the Profile card in read mode with the seeded values.')]
    public function get_member_detail_renders_profile_card_in_read_mode_with_seeded_values(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);
        $this->seedHouseholdA();

        $client->request('GET', sprintf('/admin/users/%s/%s', self::HOUSEHOLD_A, self::A_PRIMARY_ID));

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-testid="card-profile-body"]');
        self::assertSelectorTextContains('[data-testid="profile-first-name"]', 'Alice');
        self::assertSelectorTextContains('[data-testid="profile-last-name"]', 'Smith');
        self::assertSelectorTextContains('[data-testid="profile-dob"]', '1990-01-01');
        self::assertSelectorTextContains('[data-testid="profile-gender"]', 'Female');
    }

    #[Test]
    #[TestDox('GET /profile/edit returns the edit partial pre-populated with the current values.')]
    public function get_profile_edit_returns_edit_partial_pre_populated(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);
        $this->seedHouseholdA();

        $crawler = $client->request(
            'GET',
            sprintf('/admin/users/%s/%s/profile/edit', self::HOUSEHOLD_A, self::A_PRIMARY_ID),
        );

        self::assertResponseIsSuccessful();

        // Fragment, not a full page.
        $body = (string) $client->getResponse()->getContent();
        self::assertStringNotContainsStringIgnoringCase('<!doctype', $body);
        self::assertStringNotContainsStringIgnoringCase('<html', $body);

        self::assertSelectorExists('#card-profile-body form');
        self::assertSame(
            'Alice',
            $crawler->filter('input[name="update_member_profile[firstName]"]')->attr('value'),
        );
        self::assertSame(
            'Smith',
            $crawler->filter('input[name="update_member_profile[lastName]"]')->attr('value'),
        );
        self::assertSame(
            '1990-01-01',
            $crawler->filter('input[name="update_member_profile[dobIso]"]')->attr('value'),
        );
        // Gender select pre-populated to 'F'.
        $selected = $crawler->filter('select[name="update_member_profile[genderCode]"] option[selected]');
        self::assertSame('F', $selected->attr('value'));
    }

    #[Test]
    #[TestDox('POST with valid changes swaps back to read mode with the new values.')]
    public function post_profile_with_valid_changes_swaps_back_to_read_mode_with_new_values(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);
        $this->seedHouseholdA();

        $token = $this->csrfTokenFromEditForm($client);

        $client->request(
            'POST',
            sprintf('/admin/users/%s/%s/profile', self::HOUSEHOLD_A, self::A_PRIMARY_ID),
            [
                'update_member_profile' => [
                    'firstName' => 'Alicia',
                    'middleName' => 'Renee',
                    'lastName' => 'Smith-Jones',
                    'suffix' => '',
                    'dobIso' => '1991-04-05',
                    'genderCode' => 'F',
                    '_token' => $token,
                ],
            ],
        );

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-testid="card-profile-body"]');
        self::assertSelectorTextContains('[data-testid="profile-first-name"]', 'Alicia');
        self::assertSelectorTextContains('[data-testid="profile-middle-name"]', 'Renee');
        self::assertSelectorTextContains('[data-testid="profile-last-name"]', 'Smith-Jones');
        self::assertSelectorTextContains('[data-testid="profile-dob"]', '1991-04-05');

        // Verify the aggregate was actually mutated.
        $repo = static::getContainer()->get(Households::class);
        self::assertInstanceOf(Households::class, $repo);
        $household = $repo->findById(HouseholdId::fromString(self::HOUSEHOLD_A));
        $member = $this->firstMember($household);
        self::assertSame('Alicia', $member->name()->firstName);
        self::assertSame('Smith-Jones', $member->name()->lastName);
    }

    #[Test]
    #[TestDox('POST with a future DOB re-renders the edit partial at 422 with an inline error.')]
    public function post_profile_with_invalid_dob_re_renders_edit_with_inline_error(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);
        $this->seedHouseholdA();

        $token = $this->csrfTokenFromEditForm($client);

        // Clock in the test container is the real one (Symfony's default);
        // pick a year far enough in the future to be stable for the
        // foreseeable life of the test.
        $client->request(
            'POST',
            sprintf('/admin/users/%s/%s/profile', self::HOUSEHOLD_A, self::A_PRIMARY_ID),
            [
                'update_member_profile' => [
                    'firstName' => 'Alice',
                    'middleName' => '',
                    'lastName' => 'Smith',
                    'suffix' => '',
                    'dobIso' => '2999-01-01',
                    'genderCode' => 'F',
                    '_token' => $token,
                ],
            ],
        );

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorExists('#card-profile-body form');
        // The error surfaces on the dob field or at the form root; either
        // way the rendered fragment contains the not-future message text.
        $body = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('not be in the future', $body);

        // The aggregate must not have been mutated.
        $repo = static::getContainer()->get(Households::class);
        self::assertInstanceOf(Households::class, $repo);
        $household = $repo->findById(HouseholdId::fromString(self::HOUSEHOLD_A));
        $member = $this->firstMember($household);
        self::assertSame('1990-01-01', $member->dateOfBirth()->value()->format('Y-m-d'));
    }

    #[Test]
    #[TestDox('POST without a CSRF token returns 422 and leaves the aggregate unchanged.')]
    public function post_profile_without_csrf_returns_422_form_invalid(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);
        $this->seedHouseholdA();

        $client->request(
            'POST',
            sprintf('/admin/users/%s/%s/profile', self::HOUSEHOLD_A, self::A_PRIMARY_ID),
            [
                'update_member_profile' => [
                    'firstName' => 'Mallory',
                    'middleName' => '',
                    'lastName' => 'Smith',
                    'suffix' => '',
                    'dobIso' => '1990-01-01',
                    'genderCode' => 'F',
                    // No _token.
                ],
            ],
        );

        self::assertResponseStatusCodeSame(422);

        // The aggregate must not have been mutated.
        $repo = static::getContainer()->get(Households::class);
        self::assertInstanceOf(Households::class, $repo);
        $household = $repo->findById(HouseholdId::fromString(self::HOUSEHOLD_A));
        $member = $this->firstMember($household);
        self::assertSame('Alice', $member->name()->firstName);
    }

    #[Test]
    #[TestDox('POST for an unknown member id returns 404.')]
    public function post_profile_for_unknown_member_returns_404(): void
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
            sprintf('/admin/users/%s/%s/profile', self::HOUSEHOLD_A, self::UNKNOWN_MEMBER_ID),
            [
                'update_member_profile' => [
                    'firstName' => 'Ghost',
                    'middleName' => '',
                    'lastName' => 'User',
                    'suffix' => '',
                    'dobIso' => '1990-01-01',
                    'genderCode' => 'U',
                    '_token' => $token,
                ],
            ],
        );

        self::assertResponseStatusCodeSame(404);
    }

    private function csrfTokenFromEditForm(\Symfony\Bundle\FrameworkBundle\KernelBrowser $client): string
    {
        $crawler = $client->request(
            'GET',
            sprintf('/admin/users/%s/%s/profile/edit', self::HOUSEHOLD_A, self::A_PRIMARY_ID),
        );
        self::assertResponseIsSuccessful();

        $tokenField = $crawler->filter('input[name="update_member_profile[_token]"]');
        self::assertGreaterThan(0, $tokenField->count(), 'CSRF token field was not rendered in the edit form.');

        return (string) $tokenField->attr('value');
    }

    private function firstMember(Household $household): \App\Households\Domain\HouseholdMember
    {
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
            DateOfBirth::of(new DateTimeImmutable('1990-01-01'), $this->clock),
            Gender::Female,
            EmailAddress::of('alice@example.com'),
            null,
            ResidencyStatus::Resident,
            $this->clock,
        );

        $repo->save($household);
    }
}
