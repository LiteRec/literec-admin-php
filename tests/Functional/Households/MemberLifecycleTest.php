<?php

declare(strict_types=1);

namespace App\Tests\Functional\Households;

use App\Households\Domain\Household;
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
    use SignsInUsers;

    private const string TEST_USERNAME = 'member_lifecycle_e2e';

    private const string HOUSEHOLD_ID = '019571bf-5d56-7000-b500-00000000dd01';
    private const string PRIMARY_ID   = '019571bf-5d56-7000-b500-00000000dd02';
    private const string PRIMARY_CODE = 'M000700';

    private const string UNKNOWN_MEMBER_ID = '019571bf-5d56-7000-b500-0000000000fe';
    private const string UNKNOWN_HOUSEHOLD_ID = '019571bf-5d56-7000-b500-0000000000ff';

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
        $member = $this->memberById($household, self::PRIMARY_ID);
        self::assertFalse($member->isActive());
        self::assertSame(self::DEACTIVATION_REASON, $member->deactivation()?->reason);
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
        self::assertTrue($this->memberById($household, self::PRIMARY_ID)->isActive());
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

        $crawler = $client->request('GET', sprintf(self::ROUTE_MEMBER_DETAIL, self::HOUSEHOLD_ID, self::PRIMARY_ID));
        self::assertResponseIsSuccessful();
        $reactivateForm = $crawler->filter('[data-testid="profile-reactivate"]')->closest('form');
        $tokenField = $reactivateForm?->filter('input[name="_token"]');
        self::assertNotNull($tokenField);
        self::assertGreaterThan(0, $tokenField->count(), 'Reactivate CSRF token was not rendered.');
        $token = (string) $tokenField->attr('value');

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
        self::assertTrue($this->memberById($household, self::PRIMARY_ID)->isActive());
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

    private function csrfTokenFromDeactivateForm(KernelBrowser $client): string
    {
        $crawler = $client->request('GET', sprintf(self::ROUTE_DEACTIVATE, self::HOUSEHOLD_ID, self::PRIMARY_ID));
        self::assertResponseIsSuccessful();

        $tokenField = $crawler->filter('input[name="deactivate_member[_token]"]');
        self::assertGreaterThan(0, $tokenField->count(), 'Deactivate CSRF token was not rendered.');

        return (string) $tokenField->attr('value');
    }

    private function memberById(Household $household, string $memberId): \App\Households\Domain\HouseholdMember
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
        $repo = static::getContainer()->get(Households::class);
        self::assertInstanceOf(Households::class, $repo);

        $repo->save($this->buildHousehold());
    }

    private function seedDeactivatedHousehold(): void
    {
        $repo = static::getContainer()->get(Households::class);
        self::assertInstanceOf(Households::class, $repo);

        $household = $this->buildHousehold();
        $household->deactivateMember(MemberId::fromString(self::PRIMARY_ID), self::DEACTIVATION_REASON, $this->clock);
        $repo->save($household);
    }

    private function buildHousehold(): Household
    {
        return Household::register(
            HouseholdId::fromString(self::HOUSEHOLD_ID),
            HouseholdName::of('Lifecycle Family'),
            Address::of('100 Main St', null, 'Seattle', 'WA', '98101', 'US'),
            MemberId::fromString(self::PRIMARY_ID),
            MemberCode::of(self::PRIMARY_CODE),
            PersonName::of('Lee', 'Lifecycle'),
            DateOfBirth::of(new DateTimeImmutable('1990-01-01'), $this->clock),
            Gender::Female,
            EmailAddress::of('lee@example.com'),
            null,
            ResidencyStatus::Resident,
            $this->clock,
        );
    }
}
