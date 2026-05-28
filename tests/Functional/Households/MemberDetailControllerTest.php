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
 * Drives the member detail composite shell (LRA-41) through the real
 * container, the real Doctrine read model, and a freshly-seeded
 * Households aggregate. DAMA rolls each test's seeded rows back at
 * teardown.
 *
 * The card slots assert here exercise only the shell — LRA-42–45 own the
 * card content and ship their own tests.
 */
#[Large]
#[Group('database')]
final class MemberDetailControllerTest extends WebTestCase
{
    use SignsInUsers;

    /** Reused literals (SonarCloud php:S1192). */
    private const string ROUTE_MEMBER = '/admin/users/%s/%s';


    private const string TEST_USERNAME = 'member_detail_e2e';

    private const string HOUSEHOLD_A    = '019571bf-5d52-7000-b500-00000000aa01';
    private const string A_PRIMARY_ID   = '019571bf-5d52-7000-b500-00000000aa02';
    private const string A_PRIMARY_CODE = 'M000110';
    private const string HOUSEHOLD_B    = '019571bf-5d52-7000-b500-00000000bb01';
    private const string B_PRIMARY_ID   = '019571bf-5d52-7000-b500-00000000bb02';
    private const string B_PRIMARY_CODE = 'M000120';

    private const string UNKNOWN_HOUSEHOLD_ID = '019571bf-5d52-7000-b500-0000000000ff';
    private const string UNKNOWN_MEMBER_ID    = '019571bf-5d52-7000-b500-0000000000fe';

    private MockClock $clock;

    protected function setUp(): void
    {
        $this->clock = new MockClock(new DateTimeImmutable('2026-05-24 12:00:00'));
    }

    #[Test]
    #[TestDox('GET /admin/users/{h}/{m} renders the shell with all four card slots.')]
    public function get_member_detail_renders_all_four_card_slots(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);
        $this->seedHouseholdA();

        $client->request('GET', sprintf(self::ROUTE_MEMBER, self::HOUSEHOLD_A, self::A_PRIMARY_ID));

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('section[data-testid="card-household"]');
        self::assertSelectorExists('section[data-testid="card-profile"]');
        self::assertSelectorExists('section[data-testid="card-address"]');
        self::assertSelectorExists('section[data-testid="card-history"]');

        // Breadcrumb contains the household name and the member's full name.
        self::assertSelectorTextContains('nav[aria-label="Breadcrumb"]', 'Smith Family');
        self::assertSelectorTextContains('nav[aria-label="Breadcrumb"]', 'Alice Smith');

        // Header strip surfaces the member full name and the right badges.
        self::assertSelectorTextContains('header[data-testid="member-header"]', 'Alice Smith');
        self::assertSelectorExists('[data-testid="badge-active"]');
        self::assertSelectorExists('[data-testid="badge-primary"]');
    }

    #[Test]
    #[TestDox('GET /admin/users/{unknownH}/{unknownM} returns 404 when neither id maps to a household or member.')]
    public function get_member_detail_for_unknown_member_returns_404(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);

        $client->request(
            'GET',
            sprintf(self::ROUTE_MEMBER, self::UNKNOWN_HOUSEHOLD_ID, self::UNKNOWN_MEMBER_ID),
        );

        self::assertResponseStatusCodeSame(404);
    }

    #[Test]
    #[TestDox('GET /admin/users/{A}/{B.member} (member exists in a different household) returns 404.')]
    public function get_member_detail_for_member_in_different_household_returns_404(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);
        $this->seedHouseholdA();
        $this->seedHouseholdB();

        // Member B exists, but not under household A.
        $client->request(
            'GET',
            sprintf(self::ROUTE_MEMBER, self::HOUSEHOLD_A, self::B_PRIMARY_ID),
        );

        self::assertResponseStatusCodeSame(404);
    }

    #[Test]
    #[TestDox('The list page row links to the member detail route.')]
    public function row_in_list_page_links_to_member_detail_route(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);
        $this->seedHouseholdA();

        $crawler = $client->request('GET', '/admin/users');
        self::assertResponseIsSuccessful();

        $expectedHref = sprintf(self::ROUTE_MEMBER, self::HOUSEHOLD_A, self::A_PRIMARY_ID);
        $row = $crawler->filter(sprintf('tr[data-testid="member-row-%s"]', self::A_PRIMARY_ID));
        self::assertGreaterThan(0, $row->count(), 'Seeded member row was not rendered on the list page.');

        $hrefs = $row->filter('a')->each(static fn($node): string => (string) $node->attr('href'));
        self::assertContains(
            $expectedHref,
            $hrefs,
            sprintf(
                'Expected the member row to include an anchor pointing at %s; got %s.',
                $expectedHref,
                implode(', ', $hrefs),
            ),
        );
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

    private function seedHouseholdB(): void
    {
        $repo = static::getContainer()->get(Households::class);
        self::assertInstanceOf(Households::class, $repo);

        $household = Household::register(
            HouseholdId::fromString(self::HOUSEHOLD_B),
            HouseholdName::of('Lopez Household'),
            Address::of('200 Oak Ave', null, 'Portland', 'OR', '97201', 'US'),
            MemberId::fromString(self::B_PRIMARY_ID),
            MemberCode::of(self::B_PRIMARY_CODE),
            PersonName::of('Carl', 'Lopez'),
            DateOfBirth::of(new DateTimeImmutable('1985-11-30'), $this->clock),
            Gender::Male,
            EmailAddress::of('carl@example.com'),
            null,
            ResidencyStatus::Resident,
            $this->clock,
        );

        $repo->save($household);
    }
}
