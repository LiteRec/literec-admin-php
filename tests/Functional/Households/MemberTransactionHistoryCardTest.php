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
use Symfony\Component\DomCrawler\Crawler;

/**
 * Drives the Transaction History card (LRA-45) through the real
 * container, the Stub ACL adapter, and the member-detail page shell.
 * The fixtures below were chosen so the stub adapter (which seeds row
 * counts from `crc32($memberId) % 26`) produces a deterministic count
 * per case:
 *
 *   - LARGE_MEMBER_ID → 23 rows  (paginates at pageSize 20)
 *   - EMPTY_MEMBER_ID →  0 rows  (empty-state case)
 *
 * DAMA rolls back the seeded household at teardown so cases stay
 * isolated.
 */
#[Large]
#[Group('database')]
final class MemberTransactionHistoryCardTest extends WebTestCase
{
    use SignsInUsers;

    private const string TEST_USERNAME = 'history_card_e2e';
    private const string TEST_PASSWORD = 'CorrectHorseBattery!';

    private const string HOUSEHOLD_ID       = '019571bf-5d55-7000-b500-00000000dd01';
    private const string LARGE_MEMBER_ID    = '019571bf-5d55-7000-b500-000000000014';
    private const string LARGE_MEMBER_CODE  = 'M000710';

    private const string EMPTY_MEMBER_ID    = '019571bf-5d55-7000-b500-000000000013';

    private MockClock $clock;

    protected function setUp(): void
    {
        $this->clock = new MockClock(new DateTimeImmutable('2026-05-24 12:00:00'));
    }

    #[Test]
    #[TestDox('History card is collapsed and renders the lazy-load shim, not the rows, on initial page render.')]
    public function history_card_is_collapsed_and_does_not_fetch_on_initial_render(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);
        $this->seedHouseholdWithLargeMember();

        $client->request('GET', sprintf('/admin/users/%s/%s', self::HOUSEHOLD_ID, self::LARGE_MEMBER_ID));

        self::assertResponseIsSuccessful();
        // Card shell is present.
        self::assertSelectorExists('[data-testid="card-history"]');
        // The shim is present — proof the rows were NOT server-rendered.
        self::assertSelectorExists('[data-testid="history-lazy-shim"]');
        // And the rows table must NOT be present yet.
        self::assertSelectorNotExists('[data-testid="history-table"]');
        // And the body div is wired with the HTMX toggle trigger.
        $body = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('hx-trigger="toggle once from:closest details"', $body);
    }

    #[Test]
    #[TestDox('GET history page 1 returns a fragment with rows, header, and a Load more button.')]
    public function get_history_page_returns_first_page_with_rows_and_load_more(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);
        // No need to seed: the history endpoint dispatches straight to
        // the stub adapter and never asks the read model whether the
        // member exists.
        $client->request(
            'GET',
            sprintf(
                '/admin/users/%s/%s/history?page=1&pageSize=20',
                self::HOUSEHOLD_ID,
                self::LARGE_MEMBER_ID,
            ),
        );

        self::assertResponseIsSuccessful();

        // Fragment, not a full page.
        $body = (string) $client->getResponse()->getContent();
        self::assertStringNotContainsStringIgnoringCase('<!doctype', $body);
        self::assertStringNotContainsStringIgnoringCase('<html', $body);

        self::assertSelectorExists('[data-testid="history-table"]');
        // 23-row stub with pageSize 20 → 20 rows on page 1 + Load more.
        $rows = $client->getCrawler()->filter('[data-testid="history-row"]');
        self::assertSame(20, $rows->count());
        self::assertSelectorExists('[data-testid="history-loadmore"]');
    }

    #[Test]
    #[TestDox('GET history page 2 returns the remaining rows and no Load more on the final page.')]
    public function load_more_returns_next_page_with_appended_rows_and_updated_button(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);

        // Capture the page-1 row ids first so we can assert that page 2
        // returns a distinct set.
        $client->request(
            'GET',
            sprintf(
                '/admin/users/%s/%s/history?page=1&pageSize=20',
                self::HOUSEHOLD_ID,
                self::LARGE_MEMBER_ID,
            ),
        );
        self::assertResponseIsSuccessful();
        $pageOneIds = $client->getCrawler()
            ->filter('[data-testid="history-row"]')
            ->each(static fn ($node): string => (string) $node->attr('data-row-id'));
        self::assertNotEmpty($pageOneIds);

        $client->request(
            'GET',
            sprintf(
                '/admin/users/%s/%s/history?page=2&pageSize=20',
                self::HOUSEHOLD_ID,
                self::LARGE_MEMBER_ID,
            ),
        );
        self::assertResponseIsSuccessful();

        // Page 2 fragment is NOT a full table — it returns only the
        // remaining <tr> rows so the previous page's <tbody> can absorb
        // them. No fresh <thead> in this fragment.
        $body = (string) $client->getResponse()->getContent();
        self::assertStringNotContainsString('<thead', $body);

        // The DOM crawler will not see <tr> elements that live outside a
        // <table>, so we parse the fragment inside a wrapping table shell
        // to count the appended rows.
        $wrappedCrawler = new Crawler('<table><tbody>' . $body . '</tbody></table>');
        $pageTwoIds = $wrappedCrawler
            ->filter('[data-testid="history-row"]')
            ->each(static fn ($node): string => (string) $node->attr('data-row-id'));
        self::assertNotEmpty($pageTwoIds);
        // 23 rows total, pageSize 20 → page 2 carries the remaining 3.
        self::assertSame(3, count($pageTwoIds));
        // Page 2 rows must be disjoint from page 1 rows.
        self::assertSame([], array_intersect($pageOneIds, $pageTwoIds));
        // Final page → no further Load more button anywhere in the fragment.
        self::assertStringNotContainsString('data-testid="history-loadmore"', $body);
    }

    #[Test]
    #[TestDox('A member whose stub history is empty renders the empty-state copy on page 1.')]
    public function empty_history_renders_empty_state(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);

        $client->request(
            'GET',
            sprintf(
                '/admin/users/%s/%s/history?page=1&pageSize=20',
                self::HOUSEHOLD_ID,
                self::EMPTY_MEMBER_ID,
            ),
        );

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-testid="history-empty-state"]');
        self::assertSelectorNotExists('[data-testid="history-table"]');
        self::assertSelectorNotExists('[data-testid="history-loadmore"]');
    }

    #[Test]
    #[TestDox('A pageSize above the hard cap returns HTTP 400.')]
    public function history_page_size_above_cap_returns_400(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);

        $client->request(
            'GET',
            sprintf(
                '/admin/users/%s/%s/history?page=1&pageSize=999',
                self::HOUSEHOLD_ID,
                self::LARGE_MEMBER_ID,
            ),
        );

        self::assertResponseStatusCodeSame(400);
    }

    private function seedHouseholdWithLargeMember(): void
    {
        $repo = static::getContainer()->get(Households::class);
        self::assertInstanceOf(Households::class, $repo);

        $household = Household::register(
            HouseholdId::fromString(self::HOUSEHOLD_ID),
            HouseholdName::of('Sandoval Family'),
            Address::of('700 Elm St', null, 'Austin', 'TX', '78701', 'US'),
            MemberId::fromString(self::LARGE_MEMBER_ID),
            MemberCode::of(self::LARGE_MEMBER_CODE),
            PersonName::of('Sam', 'Sandoval'),
            DateOfBirth::of(new DateTimeImmutable('1985-03-15'), $this->clock),
            Gender::Other,
            EmailAddress::of('sam@example.com'),
            null,
            ResidencyStatus::Resident,
            $this->clock,
        );

        $repo->save($household);
    }
}
