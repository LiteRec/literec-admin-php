<?php

declare(strict_types=1);

namespace App\Tests\Functional\Households;

use App\Households\Domain\Household;
use App\Households\Domain\HouseholdMember;
use App\Households\Domain\Households;
use App\Households\Domain\ValueObject\AnonymizedProfile;
use App\Households\Domain\ValueObject\HouseholdId;
use App\Households\Domain\ValueObject\MemberCode;
use App\Households\Domain\ValueObject\MemberId;
use App\Tests\Support\Trait\SeedsSmithHouseholdForUi;
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
 * Drives the anonymize-member route (LRA-212) end-to-end through the real
 * container, Doctrine repositories, and command/event buses.
 *
 * DAMA rolls back rows at teardown so cases stay isolated.
 */
#[Large]
#[Group('database')]
final class AnonymizeMemberTest extends WebTestCase
{
    use SeedsSmithHouseholdForUi;
    use SignsInUsers;

    private const string PRIMARY_DOB_ISO = '1990-01-01';
    private const string PRIMARY_FULL_NAME = 'Alice Smith';

    private const string TEST_USERNAME = 'anonymize_member_e2e';

    private const string HOUSEHOLD_ID = '019571bf-5d57-7000-b500-00000000ee01';
    private const string PRIMARY_ID   = '019571bf-5d57-7000-b500-00000000ee02';
    private const string PRIMARY_CODE = 'M000800';

    private const string UNKNOWN_MEMBER_ID = '019571bf-5d57-7000-b500-0000000000fe';
    /** Stand-in survivor id: Household::mergeMemberInto() does not require it to exist. */
    private const string SURVIVOR_ID = '019571bf-5d57-7000-b500-00000000ee03';
    private const string TOKEN_HOUSEHOLD_ID = '019571bf-5d57-7000-b500-00000000ee04';
    private const string TOKEN_MEMBER_ID = '019571bf-5d57-7000-b500-00000000ee05';
    private const string TOKEN_MEMBER_CODE = 'M000802';

    private const string ROUTE_MEMBER_DETAIL = '/admin/users/%s/%s';
    private const string ROUTE_ANONYMIZE = '/admin/users/%s/%s/anonymize';

    private MockClock $clock;

    protected function setUp(): void
    {
        $this->clock = new MockClock(new DateTimeImmutable('2026-05-24 12:00:00'));
    }

    #[Test]
    #[TestDox('Renders the Anonymize Member dialog with the irreversible warning and confirmation fields.')]
    public function get_anonymize_form_renders_dialog_with_warning_and_confirmation_field(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);
        $this->seedHousehold();

        $client->request('GET', sprintf(self::ROUTE_ANONYMIZE, self::HOUSEHOLD_ID, self::PRIMARY_ID));

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('#anonymize-member-modal');
        self::assertSelectorTextContains('#anonymize-member-modal', 'This cannot be undone');
        self::assertSelectorExists('[data-testid="anonymize-confirmation"]');
        self::assertSelectorExists('[data-testid="anonymize-acknowledge"]');
    }

    #[Test]
    #[TestDox('Submitting a blank confirmation returns 422 with the inline error region rendered.')]
    public function anonymize_with_blank_confirmation_returns_422(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);
        $this->seedHousehold();

        $token = $this->csrfTokenFromAnonymizeForm($client);

        $client->request(
            'POST',
            sprintf(self::ROUTE_ANONYMIZE, self::HOUSEHOLD_ID, self::PRIMARY_ID),
            [
                'anonymize_member' => [
                    'confirmation' => '',
                    'acknowledged' => '1',
                    '_token' => $token,
                ],
            ],
        );

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorExists('[data-testid="anonymize-form-error"]');
        self::assertMemberIsNotAnonymized();
    }

    #[Test]
    #[TestDox('Submitting the wrong name returns 422 and leaves the member untouched.')]
    public function anonymize_with_wrong_name_returns_422_and_member_is_untouched(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);
        $this->seedHousehold();

        $token = $this->csrfTokenFromAnonymizeForm($client);

        $client->request(
            'POST',
            sprintf(self::ROUTE_ANONYMIZE, self::HOUSEHOLD_ID, self::PRIMARY_ID),
            [
                'anonymize_member' => [
                    'confirmation' => 'Not The Right Name',
                    'acknowledged' => '1',
                    '_token' => $token,
                ],
            ],
        );

        self::assertResponseStatusCodeSame(422);
        self::assertMemberIsNotAnonymized();
    }

    #[Test]
    #[TestDox('Submitting the correct name with the acknowledgement box unchecked returns 422.')]
    public function anonymize_with_correct_name_but_unacknowledged_returns_422(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);
        $this->seedHousehold();

        $token = $this->csrfTokenFromAnonymizeForm($client);

        $client->request(
            'POST',
            sprintf(self::ROUTE_ANONYMIZE, self::HOUSEHOLD_ID, self::PRIMARY_ID),
            [
                'anonymize_member' => [
                    'confirmation' => self::PRIMARY_FULL_NAME,
                    '_token' => $token,
                    // No 'acknowledged' key: an unchecked checkbox submits nothing.
                ],
            ],
        );

        self::assertResponseStatusCodeSame(422);
        self::assertMemberIsNotAnonymized();
    }

    #[Test]
    #[TestDox('A correct confirmation and acknowledged box redirects to member detail and persists the anonymization.')]
    public function anonymize_with_valid_confirmation_redirects_and_persists(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);
        $this->seedHousehold();

        $token = $this->csrfTokenFromAnonymizeForm($client);

        $client->request(
            'POST',
            sprintf(self::ROUTE_ANONYMIZE, self::HOUSEHOLD_ID, self::PRIMARY_ID),
            [
                'anonymize_member' => [
                    'confirmation' => self::PRIMARY_FULL_NAME,
                    'acknowledged' => '1',
                    '_token' => $token,
                ],
            ],
        );

        self::assertResponseStatusCodeSame(200);
        self::assertTrue($client->getResponse()->headers->has('HX-Redirect'));
        self::assertStringContainsString(
            sprintf(self::ROUTE_MEMBER_DETAIL, self::HOUSEHOLD_ID, self::PRIMARY_ID),
            (string) $client->getResponse()->headers->get('HX-Redirect'),
        );

        $household = $this->repo()->findById(HouseholdId::fromString(self::HOUSEHOLD_ID));
        $member = $this->memberById($household, self::PRIMARY_ID);
        self::assertTrue($member->isAnonymized());
        self::assertFalse($member->isActive());
        self::assertSame('Anonymized', $member->name()->firstName);
    }

    #[Test]
    #[TestDox('The detail page of an anonymized member shows the Anonymized badge and hides Edit/danger-zone actions.')]
    public function detail_page_of_anonymized_member_shows_anonymized_state(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);
        $this->seedAnonymizedHousehold();

        $client->request('GET', sprintf(self::ROUTE_MEMBER_DETAIL, self::HOUSEHOLD_ID, self::PRIMARY_ID));

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-testid="badge-anonymized"]');
        self::assertSelectorExists('[data-testid="profile-anonymized-at"]');
        self::assertSelectorNotExists('[data-testid="profile-edit"]');
        self::assertSelectorNotExists('[data-testid="anonymize-open"]');
        self::assertSelectorNotExists('[data-testid="profile-email"]');
        self::assertSelectorNotExists('[data-testid="contact-edit"]');
    }

    #[Test]
    #[TestDox('A second anonymize attempt on an already-anonymized member returns 409 with a no-form notice.')]
    public function second_anonymize_attempt_returns_409(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);
        $this->seedAnonymizedHousehold();

        $client->request('GET', sprintf(self::ROUTE_ANONYMIZE, self::HOUSEHOLD_ID, self::PRIMARY_ID));

        self::assertResponseStatusCodeSame(409);
        self::assertSelectorExists('[data-testid="anonymize-already-done"]');
        self::assertSelectorNotExists('[data-testid="anonymize-confirmation"]');
    }

    #[Test]
    #[TestDox('The anonymize form for a merged member returns 409 with a no-form notice instead of throwing.')]
    public function anonymize_form_for_merged_member_returns_409(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);
        $this->seedHouseholdWithMergedMember();

        $client->request('GET', sprintf(self::ROUTE_ANONYMIZE, self::HOUSEHOLD_ID, self::PRIMARY_ID));

        self::assertResponseStatusCodeSame(409);
        self::assertSelectorExists('[data-testid="anonymize-already-done"]');
        self::assertSelectorTextContains('[data-testid="anonymize-already-done"]', 'merged');
        self::assertSelectorNotExists('[data-testid="anonymize-confirmation"]');
    }

    #[Test]
    #[TestDox('Submitting anonymize for a merged member surfaces a form error instead of a 500.')]
    public function anonymize_submit_for_merged_member_returns_422(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);
        $this->seedHouseholdWithMergedMember();

        // The Profile card and Users list only hide the Anonymize action
        // for an already-merged member, and this controller's own GET now
        // returns the no-form 409 notice for one too — so there is no
        // rendered form on THIS member's page to pull a token from. The
        // 'anonymize_member' CSRF token is scoped per-session (a fixed
        // token_id, not per-member — see AnonymizeMemberFormType), so a
        // token harvested from a throwaway member's anonymize page in the
        // same session is equally valid here. Mirrors
        // MemberLifecycleTest::reactivate_merged_member_returns_409...'s
        // identical workaround for the same CSRF-scoping reason.
        $this->seedSmithHousehold(
            $this->repo(),
            HouseholdId::fromString(self::TOKEN_HOUSEHOLD_ID),
            MemberId::fromString(self::TOKEN_MEMBER_ID),
            MemberCode::of(self::TOKEN_MEMBER_CODE),
            self::PRIMARY_DOB_ISO,
            $this->clock,
        );
        $token = $this->csrfTokenFromAnonymizeForm($client, self::TOKEN_HOUSEHOLD_ID, self::TOKEN_MEMBER_ID);

        $client->request(
            'POST',
            sprintf(self::ROUTE_ANONYMIZE, self::HOUSEHOLD_ID, self::PRIMARY_ID),
            [
                'anonymize_member' => [
                    'confirmation' => self::PRIMARY_FULL_NAME,
                    'acknowledged' => '1',
                    '_token' => $token,
                ],
            ],
        );

        self::assertResponseStatusCodeSame(422);
        $household = $this->repo()->findById(HouseholdId::fromString(self::HOUSEHOLD_ID));
        self::assertFalse($this->memberById($household, self::PRIMARY_ID)->isAnonymized());
    }

    #[Test]
    #[TestDox('An anonymize POST without a CSRF token returns 422.')]
    public function anonymize_without_csrf_returns_422(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);
        $this->seedHousehold();

        $client->request(
            'POST',
            sprintf(self::ROUTE_ANONYMIZE, self::HOUSEHOLD_ID, self::PRIMARY_ID),
            [
                'anonymize_member' => [
                    'confirmation' => self::PRIMARY_FULL_NAME,
                    'acknowledged' => '1',
                    // No _token.
                ],
            ],
        );

        self::assertResponseStatusCodeSame(422);
    }

    #[Test]
    #[TestDox('Anonymizing an unknown member returns 404.')]
    public function anonymize_unknown_member_returns_404(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);
        $this->seedHousehold();

        $client->request('GET', sprintf(self::ROUTE_ANONYMIZE, self::HOUSEHOLD_ID, self::UNKNOWN_MEMBER_ID));

        self::assertResponseStatusCodeSame(404);
    }

    #[Test]
    #[TestDox('An anonymous GET to the anonymize form redirects to the login page.')]
    public function anonymous_get_redirects_to_login(): void
    {
        $client = static::createClient();
        $this->seedHousehold();

        $client->request('GET', sprintf(self::ROUTE_ANONYMIZE, self::HOUSEHOLD_ID, self::PRIMARY_ID));

        self::assertResponseRedirects();
        self::assertStringContainsString('/login', (string) $client->getResponse()->headers->get('Location'));
    }

    private function csrfTokenFromAnonymizeForm(
        KernelBrowser $client,
        ?string $householdId = null,
        ?string $memberId = null,
    ): string {
        $crawler = $client->request(
            'GET',
            sprintf(self::ROUTE_ANONYMIZE, $householdId ?? self::HOUSEHOLD_ID, $memberId ?? self::PRIMARY_ID),
        );
        self::assertResponseIsSuccessful();

        $tokenField = $crawler->filter('input[name="anonymize_member[_token]"]');
        self::assertGreaterThan(0, $tokenField->count(), 'Anonymize CSRF token was not rendered.');

        return (string) $tokenField->attr('value');
    }

    private function assertMemberIsNotAnonymized(): void
    {
        $household = $this->repo()->findById(HouseholdId::fromString(self::HOUSEHOLD_ID));
        self::assertFalse($this->memberById($household, self::PRIMARY_ID)->isAnonymized());
    }

    private function repo(): Households
    {
        $repo = static::getContainer()->get(Households::class);
        self::assertInstanceOf(Households::class, $repo);

        return $repo;
    }

    private function memberById(Household $household, string $memberId): HouseholdMember
    {
        $needle = MemberId::fromString($memberId);
        foreach ($household->members() as $member) {
            if ($member->id()->equals($needle)) {
                return $member;
            }
        }
        self::fail(sprintf('Member %s not found in household.', $memberId));
    }

    private function seedHousehold(): void
    {
        $this->seedSmithHousehold(
            $this->repo(),
            HouseholdId::fromString(self::HOUSEHOLD_ID),
            MemberId::fromString(self::PRIMARY_ID),
            MemberCode::of(self::PRIMARY_CODE),
            self::PRIMARY_DOB_ISO,
            $this->clock,
        );
    }

    private function seedAnonymizedHousehold(): void
    {
        $repo = $this->repo();
        $household = $this->seedSmithHousehold(
            $repo,
            HouseholdId::fromString(self::HOUSEHOLD_ID),
            MemberId::fromString(self::PRIMARY_ID),
            MemberCode::of(self::PRIMARY_CODE),
            self::PRIMARY_DOB_ISO,
            $this->clock,
        );
        $household->anonymizeMember(
            MemberId::fromString(self::PRIMARY_ID),
            AnonymizedProfile::placeholder(),
            $this->clock,
        );
        $repo->save($household);
    }

    private function seedHouseholdWithMergedMember(): void
    {
        $repo = $this->repo();
        $household = $this->seedSmithHousehold(
            $repo,
            HouseholdId::fromString(self::HOUSEHOLD_ID),
            MemberId::fromString(self::PRIMARY_ID),
            MemberCode::of(self::PRIMARY_CODE),
            self::PRIMARY_DOB_ISO,
            $this->clock,
        );
        $household->mergeMemberInto(
            MemberId::fromString(self::PRIMARY_ID),
            HouseholdId::fromString(self::HOUSEHOLD_ID),
            MemberId::fromString(self::SURVIVOR_ID),
            $this->clock,
        );
        $repo->save($household);
    }
}
