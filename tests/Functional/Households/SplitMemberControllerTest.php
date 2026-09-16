<?php

declare(strict_types=1);

namespace App\Tests\Functional\Households;

use App\Households\Domain\Households;
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
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\MockClock;

/**
 * Drives the split-member dialog end-to-end (LRA-209) through the real
 * container, real Doctrine repositories, and real Symfony Forms. DAMA
 * rolls back the seeded rows at teardown so cases stay isolated.
 */
#[Large]
#[Group('database')]
final class SplitMemberControllerTest extends WebTestCase
{
    use SeedsSmithHouseholdForUi;
    use SignsInUsers;

    private const string TEST_USERNAME = 'split_member_e2e';
    private const string DOB = '1990-01-01';

    private const string HOUSEHOLD_ID = '019571bf-5d55-7000-b500-00000000fa01';
    private const string SOURCE_ID    = '019571bf-5d55-7000-b500-00000000fa02';
    private const string SOURCE_CODE  = 'M000720';

    private MockClock $clock;

    protected function setUp(): void
    {
        $this->clock = new MockClock(new DateTimeImmutable('2026-05-24 12:00:00'));
    }

    #[Test]
    #[TestDox('GET the split dialog with selected transaction ids renders 200 and echoes them as hidden fields.')]
    public function form_renders_with_selected_transaction_ids(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);
        $this->seedSource();

        $crawler = $client->request('GET', $this->splitFormUrl(['txn-1', 'txn-2']));

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-testid="split-member-submit"]');
        self::assertSelectorTextContains('[data-testid="split-selected-count"]', '2 transactions');
        self::assertCount(
            2,
            $crawler->filter('input[name^="split_member[transactionIds]"]'),
        );
    }

    #[Test]
    #[TestDox('GET the split dialog with no transaction ids selected returns 400.')]
    public function form_400s_with_no_transactions_selected(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);
        $this->seedSource();

        $client->request('GET', $this->splitFormUrl([]));

        self::assertResponseStatusCodeSame(400);
    }

    #[Test]
    #[TestDox('GET the split dialog for an unknown member returns 404.')]
    public function form_404s_for_unknown_member(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);

        $client->request('GET', $this->splitFormUrl(['txn-1']));

        self::assertResponseStatusCodeSame(404);
    }

    #[Test]
    #[TestDox('POST a valid split creates a new member in the same household and redirects to it.')]
    public function post_splits_member_and_redirects(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);
        $this->seedSource();

        $crawler = $client->request('GET', $this->splitFormUrl(['txn-1', 'txn-2']));
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Split off new member')->form([
            'split_member[firstName]' => 'Bob',
            'split_member[lastName]' => 'Smith',
            'split_member[email]' => 'bob@example.com',
            'split_member[phone]' => '5550002',
        ]);
        $client->submit($form);

        self::assertResponseStatusCodeSame(200);
        $hxRedirect = (string) $client->getResponse()->headers->get('HX-Redirect');
        self::assertStringStartsWith(sprintf('/admin/users/%s/', self::HOUSEHOLD_ID), $hxRedirect);
        self::assertStringNotContainsString(self::SOURCE_ID, substr($hxRedirect, strrpos($hxRedirect, '/') ?: 0));

        $client->request('GET', $hxRedirect);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Bob Smith');
        self::assertSelectorExists(sprintf('[data-testid="household-member-row-%s"]', self::SOURCE_ID));

        // The source member is unchanged: still active, still Alice Smith.
        $repo = static::getContainer()->get(Households::class);
        self::assertInstanceOf(Households::class, $repo);
        $household = $repo->findById(HouseholdId::fromString(self::HOUSEHOLD_ID));
        self::assertCount(2, $household->members());
        $source = null;
        foreach ($household->members() as $member) {
            if ($member->id()->equals(MemberId::fromString(self::SOURCE_ID))) {
                $source = $member;
            }
        }
        self::assertNotNull($source);
        self::assertSame('Alice', $source->profile()->name->firstName);
        self::assertTrue($source->lifecycle()->isActive);
    }

    #[Test]
    #[TestDox('POST with a blank last name returns 422 with a field error.')]
    public function post_blank_last_name_returns_422(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);
        $this->seedSource();

        $crawler = $client->request('GET', $this->splitFormUrl(['txn-1']));
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Split off new member')->form([
            'split_member[firstName]' => 'Bob',
            'split_member[lastName]' => '',
        ]);
        $client->submit($form);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorExists('[data-testid="split-form-error"]');
    }

    #[Test]
    #[TestDox('POST with a blank transactionIds entry returns 422 rather than 500.')]
    public function post_blank_transaction_id_returns_422(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);
        $this->seedSource();

        $crawler = $client->request('GET', $this->splitFormUrl(['txn-1']));
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Split off new member')->form([
            'split_member[firstName]' => 'Bob',
            'split_member[lastName]' => 'Smith',
            'split_member[transactionIds][0]' => '',
        ]);
        $client->submit($form);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorExists('[data-testid="split-form-error"]');
    }

    #[Test]
    #[TestDox('POST for an unknown member returns 404.')]
    public function post_404s_for_unknown_member(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);

        $client->request('POST', sprintf(
            '/admin/users/%s/%s/split',
            self::HOUSEHOLD_ID,
            '019571bf-5d55-7000-b500-00000000fa99',
        ), [
            'split_member' => [
                'firstName' => 'Bob',
                'lastName' => 'Smith',
                'transactionIds' => ['txn-1'],
            ],
        ]);

        self::assertResponseStatusCodeSame(404);
    }

    /**
     * @param list<string> $transactionIds
     */
    private function splitFormUrl(array $transactionIds): string
    {
        $query = '';
        foreach ($transactionIds as $id) {
            $query .= '&transactionIds[]=' . urlencode($id);
        }

        return sprintf(
            '/admin/users/%s/%s/split?%s',
            self::HOUSEHOLD_ID,
            self::SOURCE_ID,
            ltrim($query, '&'),
        );
    }

    private function seedSource(): void
    {
        $repo = static::getContainer()->get(Households::class);
        self::assertInstanceOf(Households::class, $repo);

        $this->seedSmithHousehold(
            $repo,
            HouseholdId::fromString(self::HOUSEHOLD_ID),
            MemberId::fromString(self::SOURCE_ID),
            MemberCode::of(self::SOURCE_CODE),
            self::DOB,
            $this->clock,
        );
    }
}
