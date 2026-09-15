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
use Symfony\Component\DomCrawler\Crawler;

/**
 * Drives sharing (and withdrawing) a minor member with another household
 * end-to-end (LRA-210) through the real container, real Doctrine
 * repositories, and real Symfony Forms. DAMA rolls back the seeded rows
 * at teardown so cases stay isolated.
 */
#[Large]
#[Group('database')]
final class HouseholdLinkControllerTest extends WebTestCase
{
    use SignsInUsers;

    private const string TEST_USERNAME = 'household_link_e2e';

    private const string HOME_HOUSEHOLD_ID = '019571bf-5d56-7000-b500-00000000ea01';
    private const string PRIMARY_ID        = '019571bf-5d56-7000-b500-00000000ea02';
    private const string PRIMARY_CODE      = 'M000720';
    private const string MINOR_ID          = '019571bf-5d56-7000-b500-00000000ea03';
    private const string MINOR_CODE        = 'M000721';

    private const string TARGET_HOUSEHOLD_ID = '019571bf-5d56-7000-b500-00000000ea04';
    private const string TARGET_PRIMARY_ID   = '019571bf-5d56-7000-b500-00000000ea05';
    private const string TARGET_PRIMARY_CODE = 'M000722';

    private const string LINKED_HOUSEHOLDS_SELECTOR = '[data-testid="linked-households"]';

    private MockClock $clock;

    protected function setUp(): void
    {
        $this->clock = new MockClock(new DateTimeImmutable('2026-05-24 12:00:00'));
    }

    #[Test]
    #[TestDox('Link happy path: redirects to the child under the target; both households reach the detail page.')]
    public function link_happy_path_redirects_and_both_households_reach_detail(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);
        $this->seedHomeHouseholdWithMinor();
        $this->seedTargetHousehold();

        $this->postLink($client, self::MINOR_ID);

        self::assertResponseStatusCodeSame(200);
        self::assertSame(
            $this->memberDetailPath(self::TARGET_HOUSEHOLD_ID, self::MINOR_ID),
            (string) $client->getResponse()->headers->get('HX-Redirect'),
        );

        // Reachable under the target household, roster flags it Shared.
        $crawler = $client->request('GET', $this->memberDetailPath(self::TARGET_HOUSEHOLD_ID, self::MINOR_ID));
        self::assertResponseIsSuccessful();
        self::assertSelectorExists(sprintf(
            '[data-testid="household-member-row-%s"] [data-testid="badge-shared"]',
            self::MINOR_ID,
        ));
        self::assertSelectorTextContains(self::LINKED_HOUSEHOLDS_SELECTOR, 'Smith Family (Home)');
        self::assertSelectorTextContains(self::LINKED_HOUSEHOLDS_SELECTOR, 'Jones Family');

        // Identity-mutating actions (photo upload, merge) route through the
        // home household even though the page is viewed under the target —
        // AttachMemberPhotoHandler and MergeMembersHandler both load the
        // aggregate by the householdId in the request, and the minor only
        // exists in the home aggregate's own members() collection.
        $photoForm = $crawler->filter('[data-testid="photo-upload"]')->closest('form');
        self::assertNotNull($photoForm, 'Photo upload form was not rendered.');
        self::assertStringContainsString(
            $this->memberDetailPath(self::HOME_HOUSEHOLD_ID, self::MINOR_ID) . '/photo',
            (string) $photoForm->attr('hx-post'),
        );
        $mergeButton = $crawler->filter('[data-testid="merge-member"]');
        self::assertGreaterThan(0, $mergeButton->count(), 'Merge button was not rendered.');
        self::assertStringContainsString(
            $this->memberDetailPath(self::HOME_HOUSEHOLD_ID, self::MINOR_ID) . '/merge/confirm',
            (string) $mergeButton->attr('onclick'),
        );

        // Still reachable under the home household, unaffected.
        $client->request('GET', $this->memberDetailPath(self::HOME_HOUSEHOLD_ID, self::MINOR_ID));
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('[data-testid="member-header"]', 'Minor Smith');
    }

    #[Test]
    #[TestDox('Linking a member already merged into another record is rejected with 422 and the shared error region.')]
    public function link_rejects_merged_member(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);
        $this->seedHomeHouseholdWithMinor();
        $this->seedTargetHousehold();

        $repo = static::getContainer()->get(Households::class);
        self::assertInstanceOf(Households::class, $repo);
        $home = $repo->findById(HouseholdId::fromString(self::HOME_HOUSEHOLD_ID));
        $home->mergeMemberInto(
            MemberId::fromString(self::MINOR_ID),
            HouseholdId::fromString(self::HOME_HOUSEHOLD_ID),
            MemberId::fromString(self::PRIMARY_ID),
            $this->clock,
        );
        $repo->save($home);

        $this->postLink($client, self::MINOR_ID);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorExists('[data-testid="household-link-error"]');
    }

    #[Test]
    #[TestDox('Linking an adult member is rejected with 422 and the shared error region.')]
    public function link_rejects_adult_member(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);
        $this->seedHomeHouseholdWithMinor();
        $this->seedTargetHousehold();

        $this->postLink($client, self::PRIMARY_ID);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorExists('[data-testid="household-link-error"]');
    }

    #[Test]
    #[TestDox('Linking the same member to the same household twice is rejected with 422 on the second attempt.')]
    public function link_rejects_duplicate_link(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);
        $this->seedHomeHouseholdWithMinor();
        $this->seedTargetHousehold();

        $this->postLink($client, self::MINOR_ID);
        self::assertResponseStatusCodeSame(200);

        $this->postLink($client, self::MINOR_ID);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorExists('[data-testid="household-link-error"]');
    }

    #[Test]
    #[TestDox('Unlink returns the child to the home household only.')]
    public function unlink_returns_child_to_home_only(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);
        $this->seedHomeHouseholdWithMinor();
        $this->seedTargetHousehold();

        $this->postLink($client, self::MINOR_ID);
        self::assertResponseStatusCodeSame(200);

        $crawler = $client->request('GET', $this->memberDetailPath(self::TARGET_HOUSEHOLD_ID, self::MINOR_ID));
        self::assertResponseIsSuccessful();
        $token = $this->extractUnlinkToken($crawler, self::TARGET_HOUSEHOLD_ID);

        $client->request(
            'POST',
            sprintf('/admin/users/%s/members/%s/unlink', self::TARGET_HOUSEHOLD_ID, self::MINOR_ID),
            ['withdraw_minor_from_household' => ['_token' => $token]],
        );

        self::assertResponseStatusCodeSame(200);
        self::assertSame(
            $this->memberDetailPath(self::HOME_HOUSEHOLD_ID, self::MINOR_ID),
            (string) $client->getResponse()->headers->get('HX-Redirect'),
        );

        // Back on the home household's page, no linked-households list
        // (only the home entry remains, so the list does not render).
        $client->request('GET', $this->memberDetailPath(self::HOME_HOUSEHOLD_ID, self::MINOR_ID));
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists(self::LINKED_HOUSEHOLDS_SELECTOR);

        // The target household's roster no longer lists the child at all.
        $client->request('GET', $this->memberDetailPath(self::TARGET_HOUSEHOLD_ID, self::TARGET_PRIMARY_ID));
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists(sprintf('[data-testid="household-member-row-%s"]', self::MINOR_ID));
    }

    private function postLink(KernelBrowser $client, string $memberId): void
    {
        $crawler = $client->request('GET', $this->memberDetailPath(self::TARGET_HOUSEHOLD_ID, self::TARGET_PRIMARY_ID));
        self::assertResponseIsSuccessful();
        $token = $this->extractLinkToken($crawler);

        $client->request(
            'POST',
            sprintf('/admin/users/%s/members/link', self::TARGET_HOUSEHOLD_ID),
            ['link_minor_to_household' => ['memberId' => $memberId, '_token' => $token]],
        );
    }

    private function memberDetailPath(string $householdId, string $memberId): string
    {
        return sprintf('/admin/users/%s/%s', $householdId, $memberId);
    }

    /**
     * The Link Shared Member button embeds its CSRF token inline in an
     * `onclick` attribute (no rendered form — the button is wired up via
     * JS after a member-lookup-dialog selection), so it is scraped with a
     * regex rather than a `[name="..."]` field lookup.
     */
    private function extractLinkToken(Crawler $crawler): string
    {
        $button = $crawler->filter('[data-testid="link-shared-member"]');
        self::assertGreaterThan(0, $button->count(), 'Link Shared Member button was not rendered.');

        $onclick = (string) $button->attr('onclick');
        $matched = preg_match("/link_minor_to_household\\[_token\\]':\\s*'([^']+)'/", $onclick, $matches);
        self::assertSame(1, $matched, 'Could not find the link CSRF token in the button onclick attribute.');

        return $matches[1];
    }

    private function extractUnlinkToken(Crawler $crawler, string $householdId): string
    {
        $field = $crawler->filter(sprintf(
            '[data-testid="unlink-household-%s"]',
            $householdId,
        ))->closest('form')?->filter('input[name="withdraw_minor_from_household[_token]"]');

        self::assertNotNull($field, 'Unlink form was not rendered.');
        self::assertGreaterThan(0, $field->count(), 'Unlink CSRF token field was not rendered.');

        return (string) $field->attr('value');
    }

    private function seedHomeHouseholdWithMinor(): void
    {
        $repo = static::getContainer()->get(Households::class);
        self::assertInstanceOf(Households::class, $repo);

        $household = Household::register(
            HouseholdId::fromString(self::HOME_HOUSEHOLD_ID),
            HouseholdName::of('Smith Family'),
            Address::of('100 Main St', 'Apt 2B', 'Seattle', 'WA', '98101', 'US'),
            MemberId::fromString(self::PRIMARY_ID),
            MemberCode::of(self::PRIMARY_CODE),
            PersonName::of('Alice', 'Smith'),
            DateOfBirth::of(new DateTimeImmutable('1990-01-01'), $this->clock),
            Gender::Female,
            EmailAddress::of('alice@example.com'),
            null,
            ResidencyStatus::Resident,
            $this->clock,
        );

        $household->addMember(
            MemberId::fromString(self::MINOR_ID),
            MemberCode::of(self::MINOR_CODE),
            PersonName::of('Minor', 'Smith'),
            DateOfBirth::of(new DateTimeImmutable('2015-01-01'), $this->clock),
            Gender::Male,
            null,
            null,
            ResidencyStatus::Resident,
            false,
            $this->clock,
        );

        $repo->save($household);
    }

    private function seedTargetHousehold(): void
    {
        $repo = static::getContainer()->get(Households::class);
        self::assertInstanceOf(Households::class, $repo);

        $household = Household::register(
            HouseholdId::fromString(self::TARGET_HOUSEHOLD_ID),
            HouseholdName::of('Jones Family'),
            Address::of('200 Oak Ave', null, 'Portland', 'OR', '97201', 'US'),
            MemberId::fromString(self::TARGET_PRIMARY_ID),
            MemberCode::of(self::TARGET_PRIMARY_CODE),
            PersonName::of('Bob', 'Jones'),
            DateOfBirth::of(new DateTimeImmutable('1978-01-01'), $this->clock),
            Gender::Male,
            EmailAddress::of('bob@example.com'),
            null,
            ResidencyStatus::Resident,
            $this->clock,
        );

        $repo->save($household);
    }
}
