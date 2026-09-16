<?php

declare(strict_types=1);

namespace App\Tests\Functional\Households;

use App\Households\Domain\Households;
use App\Households\Domain\ValueObject\HouseholdId;
use App\Households\Domain\ValueObject\MemberCode;
use App\Households\Domain\ValueObject\MemberId;
use App\Tests\Support\Trait\SeedsAliceSmithHousehold;
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
 * Drives the deactivate/reactivate member routes (LRA-211) end-to-end
 * through the real container, Doctrine repositories, and command/event
 * buses.
 *
 * DAMA rolls back rows at teardown so cases stay isolated.
 */
#[Large]
#[Group('database')]
final class MemberLifecycleTest extends WebTestCase
{
    use SeedsAliceSmithHousehold;
    use SeedsSmithHouseholdForUi;
    use SignsInUsers;

    private const string PRIMARY_DOB_ISO = '1990-01-01';

    private const string TEST_USERNAME = 'member_lifecycle_e2e';

    private const string HOUSEHOLD_ID = '019571bf-5d56-7000-b500-00000000dd01';
    private const string PRIMARY_ID   = '019571bf-5d56-7000-b500-00000000dd02';
    private const string PRIMARY_CODE = 'M000700';

    private const string UNKNOWN_MEMBER_ID = '019571bf-5d56-7000-b500-0000000000fe';
    private const string UNKNOWN_HOUSEHOLD_ID = '019571bf-5d56-7000-b500-0000000000ff';

    /** Stand-in survivor id: MemberInHousehold::mergeInto() does not require it to exist. */
    private const string SURVIVOR_ID = '019571bf-5d56-7000-b500-00000000dd03';

    /** Throwaway fixture used only to harvest a valid 'reactivate_member' CSRF token. */
    private const string TOKEN_HOUSEHOLD_ID = '019571bf-5d56-7000-b500-00000000dd04';
    private const string TOKEN_MEMBER_ID    = '019571bf-5d56-7000-b500-00000000dd05';
    private const string TOKEN_MEMBER_CODE  = 'M000701';

    private const string ROUTE_MEMBER_DETAIL = '/admin/users/%s/%s';
    private const string ROUTE_DEACTIVATE = '/admin/users/%s/%s/deactivate';
    private const string ROUTE_REACTIVATE = '/admin/users/%s/%s/reactivate';

    /** Reused literal (SonarCloud php:S1192). */
    private const string DEACTIVATION_REASON = 'Moved out of state';

    private MockClock $clock;

    protected function setUp(): void
    {
        $this->clock = new MockClock(new DateTimeImmutable('2026-05-24 12:00:00'));
    }

    #[Test]
    #[TestDox('Renders the Deactivate Member dialog with the reason field for an active member.')]
    public function get_deactivate_form_renders_dialog_with_reason_field(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);
        $this->seedHousehold();

        $client->request('GET', sprintf(self::ROUTE_DEACTIVATE, self::HOUSEHOLD_ID, self::PRIMARY_ID));

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('#deactivate-member-modal');
        self::assertSelectorExists(
            'input[name="deactivate_member[reason]"], textarea[name="deactivate_member[reason]"]',
        );
    }

    #[Test]
    #[TestDox('Deactivating with a valid reason redirects to member detail and persists the deactivation.')]
    public function deactivate_with_valid_reason_redirects_and_persists(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);
        $this->seedHousehold();

        $token = $this->csrfTokenFromDeactivateForm($client);

        $client->request(
            'POST',
            sprintf(self::ROUTE_DEACTIVATE, self::HOUSEHOLD_ID, self::PRIMARY_ID),
            [
                'deactivate_member' => [
                    'reason' => self::DEACTIVATION_REASON,
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

        $repo = static::getContainer()->get(Households::class);
        self::assertInstanceOf(Households::class, $repo);
        $household = $repo->findById(HouseholdId::fromString(self::HOUSEHOLD_ID));
        $lifecycle = $this->memberById($household, self::PRIMARY_ID)->lifecycle();
        self::assertFalse($lifecycle->isActive);
        self::assertSame(self::DEACTIVATION_REASON, $lifecycle->deactivation?->reason);
    }

    #[Test]
    #[TestDox('Submitting a blank reason returns 422 with the inline error region rendered.')]
    public function deactivate_with_blank_reason_returns_422(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);
        $this->seedHousehold();

        $token = $this->csrfTokenFromDeactivateForm($client);

        $client->request(
            'POST',
            sprintf(self::ROUTE_DEACTIVATE, self::HOUSEHOLD_ID, self::PRIMARY_ID),
            [
                'deactivate_member' => [
                    'reason' => '',
                    '_token' => $token,
                ],
            ],
        );

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorExists('[data-testid="deactivate-form-error"]');

        $repo = static::getContainer()->get(Households::class);
        self::assertInstanceOf(Households::class, $repo);
        $household = $repo->findById(HouseholdId::fromString(self::HOUSEHOLD_ID));
        self::assertTrue($this->memberById($household, self::PRIMARY_ID)->lifecycle()->isActive);
    }

    #[Test]
    #[TestDox('A deactivate POST without a CSRF token returns 422.')]
    public function deactivate_without_csrf_returns_422(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);
        $this->seedHousehold();

        $client->request(
            'POST',
            sprintf(self::ROUTE_DEACTIVATE, self::HOUSEHOLD_ID, self::PRIMARY_ID),
            [
                'deactivate_member' => [
                    'reason' => self::DEACTIVATION_REASON,
                    // No _token.
                ],
            ],
        );

        self::assertResponseStatusCodeSame(422);
    }

    #[Test]
    #[TestDox('Deactivating an unknown member returns 404.')]
    public function deactivate_unknown_member_returns_404(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);
        $this->seedHousehold();

        $client->request('GET', sprintf(self::ROUTE_DEACTIVATE, self::HOUSEHOLD_ID, self::UNKNOWN_MEMBER_ID));

        self::assertResponseStatusCodeSame(404);
    }

    #[Test]
    #[TestDox('Deactivating an unknown household returns 404.')]
    public function deactivate_unknown_household_returns_404(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);
        $this->seedHousehold();

        $client->request('GET', sprintf(self::ROUTE_DEACTIVATE, self::UNKNOWN_HOUSEHOLD_ID, self::PRIMARY_ID));

        self::assertResponseStatusCodeSame(404);
    }

    #[Test]
    #[TestDox('The detail page for a deactivated member shows the deactivated badge, reason, and Reactivate action.')]
    public function detail_page_of_deactivated_member_shows_deactivation_state(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);
        $this->seedDeactivatedHousehold();

        $client->request('GET', sprintf(self::ROUTE_MEMBER_DETAIL, self::HOUSEHOLD_ID, self::PRIMARY_ID));

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-testid="badge-deactivated"]');
        self::assertSelectorTextContains('[data-testid="profile-deactivated-reason"]', self::DEACTIVATION_REASON);
        self::assertSelectorExists('[data-testid="profile-reactivate"]');
        self::assertSelectorNotExists('[data-testid="profile-deactivate"]');
    }

    #[Test]
    #[TestDox('Reactivating with a valid CSRF token redirects to member detail and the member is active again.')]
    public function reactivate_with_valid_token_redirects_and_reactivates(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);
        $this->seedDeactivatedHousehold();

        $token = $this->csrfTokenFromReactivateButton($client, self::HOUSEHOLD_ID, self::PRIMARY_ID);

        $client->request(
            'POST',
            sprintf(self::ROUTE_REACTIVATE, self::HOUSEHOLD_ID, self::PRIMARY_ID),
            ['_token' => $token],
        );

        self::assertResponseStatusCodeSame(200);
        self::assertTrue($client->getResponse()->headers->has('HX-Redirect'));

        $repo = static::getContainer()->get(Households::class);
        self::assertInstanceOf(Households::class, $repo);
        $household = $repo->findById(HouseholdId::fromString(self::HOUSEHOLD_ID));
        self::assertTrue($this->memberById($household, self::PRIMARY_ID)->lifecycle()->isActive);
    }

    #[Test]
    #[TestDox('Reactivating with a bad CSRF token returns 403.')]
    public function reactivate_with_bad_token_returns_403(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);
        $this->seedDeactivatedHousehold();

        $client->request(
            'POST',
            sprintf(self::ROUTE_REACTIVATE, self::HOUSEHOLD_ID, self::PRIMARY_ID),
            ['_token' => 'not-a-real-token'],
        );

        self::assertResponseStatusCodeSame(403);
    }

    #[Test]
    #[TestDox('Deactivating a merged member returns 422 with the error region rendered; merged state is unchanged.')]
    public function deactivate_merged_member_returns_422_and_leaves_merged_state_unchanged(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);
        $this->seedMergedHousehold();

        $token = $this->csrfTokenFromDeactivateForm($client);

        $client->request(
            'POST',
            sprintf(self::ROUTE_DEACTIVATE, self::HOUSEHOLD_ID, self::PRIMARY_ID),
            [
                'deactivate_member' => [
                    'reason' => self::DEACTIVATION_REASON,
                    '_token' => $token,
                ],
            ],
        );

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorExists('[data-testid="deactivate-form-error"]');

        $repo = static::getContainer()->get(Households::class);
        self::assertInstanceOf(Households::class, $repo);
        $household = $repo->findById(HouseholdId::fromString(self::HOUSEHOLD_ID));
        $member = $this->memberById($household, self::PRIMARY_ID);
        self::assertTrue($member->lifecycle()->isActive);
        self::assertTrue($member->lifecycle()->isMerged());
    }

    #[Test]
    #[TestDox('Reactivating a merged member returns 409, and merged state is unchanged.')]
    public function reactivate_merged_member_returns_409_and_leaves_merged_state_unchanged(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);
        $this->seedMergedHousehold();

        // The Profile card hides the Reactivate button entirely for a merged
        // member (same gate the bug report is about), so there is no
        // rendered form on THIS member's page to pull a token from. The
        // 'reactivate_member' CSRF token is scoped per-session, not per
        // member, so a token harvested from any other deactivated member's
        // page in the same session is equally valid here — seed a
        // throwaway one purely to harvest it.
        $this->seedDeactivatedHousehold(self::TOKEN_HOUSEHOLD_ID, self::TOKEN_MEMBER_ID, self::TOKEN_MEMBER_CODE);
        $token = $this->csrfTokenFromReactivateButton($client, self::TOKEN_HOUSEHOLD_ID, self::TOKEN_MEMBER_ID);

        $client->request(
            'POST',
            sprintf(self::ROUTE_REACTIVATE, self::HOUSEHOLD_ID, self::PRIMARY_ID),
            ['_token' => $token],
        );

        self::assertResponseStatusCodeSame(409);

        $repo = static::getContainer()->get(Households::class);
        self::assertInstanceOf(Households::class, $repo);
        $household = $repo->findById(HouseholdId::fromString(self::HOUSEHOLD_ID));
        $member = $this->memberById($household, self::PRIMARY_ID);
        self::assertTrue($member->lifecycle()->isActive);
        self::assertTrue($member->lifecycle()->isMerged());
    }

    private function csrfTokenFromDeactivateForm(KernelBrowser $client): string
    {
        $crawler = $client->request('GET', sprintf(self::ROUTE_DEACTIVATE, self::HOUSEHOLD_ID, self::PRIMARY_ID));
        self::assertResponseIsSuccessful();

        $tokenField = $crawler->filter('input[name="deactivate_member[_token]"]');
        self::assertGreaterThan(0, $tokenField->count(), 'Deactivate CSRF token was not rendered.');

        return (string) $tokenField->attr('value');
    }

    private function csrfTokenFromReactivateButton(KernelBrowser $client, string $householdId, string $memberId): string
    {
        $crawler = $client->request('GET', sprintf(self::ROUTE_MEMBER_DETAIL, $householdId, $memberId));
        self::assertResponseIsSuccessful();

        $reactivateForm = $crawler->filter('[data-testid="profile-reactivate"]')->closest('form');
        $tokenField = $reactivateForm?->filter('input[name="_token"]');
        self::assertNotNull($tokenField);
        self::assertGreaterThan(0, $tokenField->count(), 'Reactivate CSRF token was not rendered.');

        return (string) $tokenField->attr('value');
    }

    private function seedHousehold(): void
    {
        $repo = static::getContainer()->get(Households::class);
        self::assertInstanceOf(Households::class, $repo);

        $this->seedSmithHousehold(
            $repo,
            HouseholdId::fromString(self::HOUSEHOLD_ID),
            MemberId::fromString(self::PRIMARY_ID),
            MemberCode::of(self::PRIMARY_CODE),
            self::PRIMARY_DOB_ISO,
            $this->clock,
        );
    }

    private function seedDeactivatedHousehold(
        string $householdId = self::HOUSEHOLD_ID,
        string $memberId = self::PRIMARY_ID,
        string $memberCode = self::PRIMARY_CODE,
    ): void {
        $repo = static::getContainer()->get(Households::class);
        self::assertInstanceOf(Households::class, $repo);

        $household = $this->seedSmithHousehold(
            $repo,
            HouseholdId::fromString($householdId),
            MemberId::fromString($memberId),
            MemberCode::of($memberCode),
            self::PRIMARY_DOB_ISO,
            $this->clock,
        );
        $household->member(MemberId::fromString($memberId))->deactivate(self::DEACTIVATION_REASON, $this->clock);
        $repo->save($household);
    }

    private function seedMergedHousehold(): void
    {
        $repo = static::getContainer()->get(Households::class);
        self::assertInstanceOf(Households::class, $repo);

        $household = $this->seedSmithHousehold(
            $repo,
            HouseholdId::fromString(self::HOUSEHOLD_ID),
            MemberId::fromString(self::PRIMARY_ID),
            MemberCode::of(self::PRIMARY_CODE),
            self::PRIMARY_DOB_ISO,
            $this->clock,
        );
        $household->member(MemberId::fromString(self::PRIMARY_ID))->mergeInto(
            HouseholdId::fromString(self::HOUSEHOLD_ID),
            MemberId::fromString(self::SURVIVOR_ID),
            $this->clock,
        );
        $repo->save($household);
    }
}
