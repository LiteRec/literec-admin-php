<?php

declare(strict_types=1);

namespace App\Tests\Functional\Households;

use App\Households\Domain\Household;
use App\Households\Domain\Households;
use App\Households\Domain\ValueObject\Address;
use App\Households\Domain\ValueObject\DateOfBirth;
use App\Shared\Domain\ValueObject\EmailAddress;
use App\Households\Domain\ValueObject\Gender;
use App\Households\Domain\ValueObject\HouseholdId;
use App\Households\Domain\ValueObject\HouseholdName;
use App\Households\Domain\ValueObject\MemberCode;
use App\Households\Domain\ValueObject\MemberId;
use App\Households\Domain\ValueObject\PersonName;
use App\Shared\Domain\ValueObject\PhoneNumber;
use App\Households\Domain\ValueObject\ResidencyStatus;
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
 * Drives the View Members list page (LRA-39) through the real container,
 * the real Doctrine read model, and a freshly-seeded Households aggregate.
 *
 * DAMA's PHPUnit extension wraps each test in a transaction rolled back at
 * teardown, so seeded rows are isolated between tests without manual
 * truncation. Identifiers use UUID v7 values so they sort deterministically
 * across runs.
 */
#[Large]
#[Group('database')]
final class SearchMembersControllerTest extends WebTestCase
{
    use SignsInUsers;

    /** Reused literals (SonarCloud php:S1192). */
    private const string ROUTE_MEMBERS = '/admin/users';
    private const string SEL_MEMBER_ROW = 'tr[data-testid="member-row-%s"]';


    private const string TEST_USERNAME = 'members_list_e2e';

    private const string HOUSEHOLD_A     = '019571bf-5d51-7000-b500-00000000aa01';
    private const string HOUSEHOLD_B     = '019571bf-5d51-7000-b500-00000000bb01';
    private const string A_PRIMARY_ID    = '019571bf-5d51-7000-b500-00000000aa02';
    private const string A_PRIMARY_CODE  = 'M000010';
    private const string A_SECOND_ID     = '019571bf-5d51-7000-b500-00000000aa03';
    private const string A_SECOND_CODE   = 'M000011';
    private const string B_PRIMARY_ID    = '019571bf-5d51-7000-b500-00000000bb02';
    private const string B_PRIMARY_CODE  = 'M000020';

    private MockClock $clock;

    protected function setUp(): void
    {
        $this->clock = new MockClock(new DateTimeImmutable('2026-05-24 12:00:00'));
    }

    #[Test]
    #[TestDox('GET /admin/users renders 200 with a row per seeded member.')]
    public function get_users_index_returns_200_and_shows_seeded_rows(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);
        $this->seedTwoHouseholds();

        $client->request('GET', self::ROUTE_MEMBERS);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main h1', 'Users');
        self::assertSelectorExists(sprintf(self::SEL_MEMBER_ROW, self::A_PRIMARY_ID));
        self::assertSelectorExists(sprintf(self::SEL_MEMBER_ROW, self::A_SECOND_ID));
        self::assertSelectorExists(sprintf(self::SEL_MEMBER_ROW, self::B_PRIMARY_ID));
        // Nav highlights the Users section.
        self::assertSelectorExists(
            'nav[aria-label="Main navigation"] [role="menuitem"][href="/admin/users"].bg-litrec-primary',
        );
    }

    #[Test]
    #[TestDox('GET /admin/users/_table?lastName=Smith returns a partial containing only matching rows.')]
    public function filter_by_lastname_narrows_results_via_partial(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);
        $this->seedTwoHouseholds();

        $client->request('GET', '/admin/users/_table?lastName=Smith');

        self::assertResponseIsSuccessful();
        $response = $client->getResponse();
        self::assertStringContainsString('text/html', (string) $response->headers->get('Content-Type'));
        $body = (string) $response->getContent();
        self::assertSame(
            0,
            preg_match('/<!doctype\b|<html\b/i', $body),
            'Partial response must not include the full HTML shell.',
        );

        // A_PRIMARY (Alice Smith) and B_PRIMARY (Carl Smith) match; A_SECOND (Bob Brown) does not.
        self::assertStringContainsString('member-row-' . self::A_PRIMARY_ID, $body);
        self::assertStringContainsString('member-row-' . self::B_PRIMARY_ID, $body);
        self::assertStringNotContainsString('member-row-' . self::A_SECOND_ID, $body);
    }

    #[Test]
    #[TestDox('Pagination footer advances from page 1 to page 2 when there are more rows than pageSize.')]
    public function pagination_advances_to_page_two(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);
        $this->seedLargeHousehold(25);

        $client->request('GET', '/admin/users?pageSize=20');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('[data-testid="pagination-status"]', 'Page 1 of 2');

        $client->request('GET', '/admin/users?pageSize=20&page=2');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('[data-testid="pagination-status"]', 'Page 2 of 2');

        // 5 rows on the second page (25 total, pageSize 20).
        $crawler = $client->getCrawler();
        self::assertCount(5, $crawler->filter('tr[data-testid^="member-row-"]'));
    }

    #[Test]
    #[TestDox('With no seeded data the page renders the empty state message.')]
    public function empty_filters_with_no_data_shows_empty_state(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);

        $client->request('GET', self::ROUTE_MEMBERS);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', 'No members match your filters.');
    }

    #[Test]
    #[TestDox('Regression: the placeholder "coming soon" stub no longer renders at /admin/users.')]
    public function removed_placeholder_is_gone(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);

        $client->request('GET', self::ROUTE_MEMBERS);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextNotContains('main', 'Coming soon');
    }

    private function seedTwoHouseholds(): void
    {
        $repo = static::getContainer()->get(Households::class);
        self::assertInstanceOf(Households::class, $repo);

        $repo->save($this->buildHouseholdA());
        $repo->save($this->buildHouseholdB());
    }

    private function seedLargeHousehold(int $memberCount): void
    {
        $repo = static::getContainer()->get(Households::class);
        self::assertInstanceOf(Households::class, $repo);

        // The last UUID-v7 group is 12 hex chars; we keep a 10-char common
        // prefix and use the trailing 2 chars as a stable per-member suffix
        // so identifiers sort predictably across a 25-row page.
        $idAt = static fn (int $n): string => sprintf('019571bf-5d51-7000-b500-00000000cc%02x', $n);

        $household = Household::register(
            HouseholdId::fromString($idAt(1)),
            HouseholdName::of('Paginated Family'),
            Address::of('1 Pagination Way', null, 'Seattle', 'WA', '98101', 'US'),
            MemberId::fromString($idAt(2)),
            MemberCode::of('M001000'),
            PersonName::of('Primary', 'Family'),
            DateOfBirth::of(new DateTimeImmutable('1980-01-01'), $this->clock),
            Gender::Female,
            EmailAddress::of('primary@example.com'),
            null,
            ResidencyStatus::Resident,
            $this->clock,
        );

        // Members start at suffix 0x10 to leave room (and clearly separate
        // from the household + primary identifiers at the low end).
        for ($i = 2; $i <= $memberCount; $i++) {
            $household->addMember(
                MemberId::fromString($idAt(0x10 + $i)),
                MemberCode::of(sprintf('M%06d', 1000 + $i)),
                PersonName::of(sprintf('First%02d', $i), sprintf('Last%02d', $i)),
                DateOfBirth::of(new DateTimeImmutable('1990-06-15'), $this->clock),
                Gender::Other,
                null,
                null,
                ResidencyStatus::Resident,
                false,
                $this->clock,
            );
        }

        $repo->save($household);
    }

    private function buildHouseholdA(): Household
    {
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

        $household->addMember(
            MemberId::fromString(self::A_SECOND_ID),
            MemberCode::of(self::A_SECOND_CODE),
            PersonName::of('Bob', 'Brown'),
            DateOfBirth::of(new DateTimeImmutable('1992-03-04'), $this->clock),
            Gender::Male,
            null,
            PhoneNumber::of('5550002'),
            ResidencyStatus::NonResident,
            false,
            $this->clock,
        );

        return $household;
    }

    private function buildHouseholdB(): Household
    {
        return Household::register(
            HouseholdId::fromString(self::HOUSEHOLD_B),
            HouseholdName::of('Smith-Lopez Household'),
            Address::of('200 Oak Ave', null, 'Portland', 'OR', '97201', 'US'),
            MemberId::fromString(self::B_PRIMARY_ID),
            MemberCode::of(self::B_PRIMARY_CODE),
            PersonName::of('Carl', 'Smith'),
            DateOfBirth::of(new DateTimeImmutable('1985-11-30'), $this->clock),
            Gender::Male,
            EmailAddress::of('carl@example.com'),
            PhoneNumber::of('5550100'),
            ResidencyStatus::Member,
            $this->clock,
        );
    }
}
