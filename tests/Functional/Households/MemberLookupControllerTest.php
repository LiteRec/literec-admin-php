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
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\MockClock;

/**
 * Drives the reusable Member Lookup endpoint (LRA-46) through the real
 * container, the real Doctrine read model, and a freshly-seeded Households
 * aggregate. Mirrors the seeding pattern from SearchMembersControllerTest so
 * the two endpoints stay observable side-by-side.
 *
 * DAMA wraps each test in a transaction rolled back at teardown — no manual
 * cleanup is required between tests.
 */
#[Large]
#[Group('database')]
final class MemberLookupControllerTest extends WebTestCase
{
    use SignsInUsers;

    private const string TEST_USERNAME = 'member_lookup_e2e';

    private const string HOUSEHOLD_A    = '019571bf-6d51-7000-b500-00000000aa01';
    private const string HOUSEHOLD_B    = '019571bf-6d51-7000-b500-00000000bb01';
    private const string A_PRIMARY_ID   = '019571bf-6d51-7000-b500-00000000aa02';
    private const string A_PRIMARY_CODE = 'M000110';
    private const string A_SECOND_ID    = '019571bf-6d51-7000-b500-00000000aa03';
    private const string A_SECOND_CODE  = 'M000111';
    private const string B_PRIMARY_ID   = '019571bf-6d51-7000-b500-00000000bb02';
    private const string B_PRIMARY_CODE = 'M000120';

    private MockClock $clock;

    protected function setUp(): void
    {
        $this->clock = new MockClock(new DateTimeImmutable('2026-05-24 12:00:00'));
    }

    #[Test]
    #[TestDox('GET /admin/users/_lookup with no filters returns a fragment containing a row per seeded member.')]
    public function lookup_search_with_empty_filters_returns_seeded_members(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);
        $this->seedTwoHouseholds();

        $client->request('GET', '/admin/users/_lookup');

        self::assertResponseIsSuccessful();
        $body = (string) $client->getResponse()->getContent();
        self::assertSame(
            0,
            preg_match('/<!doctype\b|<html\b/i', $body),
            'Lookup response must be an HTML fragment, not a full page.',
        );

        self::assertStringContainsString('member-lookup-row-' . self::A_PRIMARY_ID, $body);
        self::assertStringContainsString('member-lookup-row-' . self::A_SECOND_ID, $body);
        self::assertStringContainsString('member-lookup-row-' . self::B_PRIMARY_ID, $body);
    }

    #[Test]
    #[TestDox('GET /admin/users/_lookup?lastName=Smith narrows results to matching members only.')]
    public function lookup_search_filter_by_lastname_narrows_results(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);
        $this->seedTwoHouseholds();

        $client->request('GET', '/admin/users/_lookup?lastName=Smith');

        self::assertResponseIsSuccessful();
        $body = (string) $client->getResponse()->getContent();

        // Alice Smith (A_PRIMARY) and Carl Smith (B_PRIMARY) match; Bob Brown
        // (A_SECOND) does not.
        self::assertStringContainsString('member-lookup-row-' . self::A_PRIMARY_ID, $body);
        self::assertStringContainsString('member-lookup-row-' . self::B_PRIMARY_ID, $body);
        self::assertStringNotContainsString('member-lookup-row-' . self::A_SECOND_ID, $body);
    }

    #[Test]
    #[TestDox('GET /admin/users/_lookup with pageSize above the lookup cap returns HTTP 400.')]
    public function lookup_search_with_pageSize_above_cap_returns_400(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);

        $client->request('GET', '/admin/users/_lookup?pageSize=200');

        self::assertResponseStatusCodeSame(400);
    }

    #[Test]
    #[TestDox('GET /admin/users/_lookup with no data renders the "No members match." empty state.')]
    public function lookup_search_empty_results_renders_empty_state(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);

        $client->request('GET', '/admin/users/_lookup');

        self::assertResponseIsSuccessful();
        $body = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('No members match.', $body);
        self::assertStringContainsString('member-lookup-empty', $body);
    }

    #[Test]
    #[TestDox('Each lookup row carries data-* attributes for the member-selected payload.')]
    public function lookup_row_carries_member_payload_attributes(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);
        $this->seedTwoHouseholds();

        $client->request('GET', '/admin/users/_lookup?memberCode=' . self::A_PRIMARY_CODE);

        self::assertResponseIsSuccessful();
        $crawler = $client->getCrawler();
        $row = $crawler->filter(sprintf('[data-testid="member-lookup-row-%s"]', self::A_PRIMARY_ID));
        self::assertSame(1, $row->count(), 'Expected a single matching lookup row.');

        self::assertSame(self::A_PRIMARY_ID, $row->attr('data-member-id'));
        self::assertSame(self::HOUSEHOLD_A, $row->attr('data-household-id'));
        self::assertSame('Alice Smith', $row->attr('data-full-name'));
        self::assertSame(self::A_PRIMARY_CODE, $row->attr('data-code'));
    }

    #[Test]
    #[TestDox('When both `code` and `memberCode` are sent, `code` wins (documented alias precedence).')]
    public function lookup_prefers_code_over_memberCode_when_both_present(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);
        $this->seedTwoHouseholds();

        // memberCode points at A's primary; code points at B's primary.
        // The controller documents `code` as the winner.
        $client->request(
            'GET',
            sprintf(
                '/admin/users/_lookup?memberCode=%s&code=%s',
                self::A_PRIMARY_CODE,
                self::B_PRIMARY_CODE,
            ),
        );

        self::assertResponseIsSuccessful();
        $body = (string) $client->getResponse()->getContent();

        // B (the `code` value) is present; A (the `memberCode` value) is not.
        self::assertStringContainsString('member-lookup-row-' . self::B_PRIMARY_ID, $body);
        self::assertStringNotContainsString('member-lookup-row-' . self::A_PRIMARY_ID, $body);
    }

    private function seedTwoHouseholds(): void
    {
        $repo = static::getContainer()->get(Households::class);
        self::assertInstanceOf(Households::class, $repo);

        $repo->save($this->buildHouseholdA());
        $repo->save($this->buildHouseholdB());
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
