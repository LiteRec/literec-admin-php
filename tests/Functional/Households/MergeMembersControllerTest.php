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
use App\Households\Domain\ValueObject\MemberContact;
use App\Households\Domain\ValueObject\MemberId;
use App\Households\Domain\ValueObject\MemberProfile;
use App\Households\Domain\ValueObject\PersonName;
use App\Households\Domain\ValueObject\ResidencyStatus;
use App\Shared\Domain\ValueObject\EmailAddress;
use App\Shared\Domain\ValueObject\PhoneNumber;
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
 * Drives the merge-members dialog end-to-end (LRA-208) through the real
 * container, real Doctrine repositories, and real Symfony Forms. DAMA
 * rolls back the seeded rows at teardown so cases stay isolated.
 */
#[Large]
#[Group('database')]
final class MergeMembersControllerTest extends WebTestCase
{
    use SeedsSmithHouseholdForUi;
    use SignsInUsers;

    private const string TEST_USERNAME = 'merge_members_e2e';
    private const string DOB = '1990-01-01';

    private const string SURVIVOR_HOUSEHOLD_ID = '019571bf-5d55-7000-b500-00000000da01';
    private const string SURVIVOR_MEMBER_ID    = '019571bf-5d55-7000-b500-00000000da02';
    private const string SURVIVOR_MEMBER_CODE  = 'M000710';

    private const string DUPLICATE_HOUSEHOLD_ID = '019571bf-5d55-7000-b500-00000000da03';
    private const string DUPLICATE_MEMBER_ID    = '019571bf-5d55-7000-b500-00000000da04';
    private const string DUPLICATE_MEMBER_CODE  = 'M000711';

    private MockClock $clock;

    protected function setUp(): void
    {
        $this->clock = new MockClock(new DateTimeImmutable('2026-05-24 12:00:00'));
    }

    #[Test]
    #[TestDox('GET the confirm dialog renders both the survivor and the duplicate side by side.')]
    public function confirm_dialog_renders_both_records(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);
        $this->seedSurvivor();
        $this->seedDuplicate(null);

        $client->request('GET', $this->confirmUrl());

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-testid="merge-survivor-summary"]');
        self::assertSelectorExists('[data-testid="merge-duplicate-summary"]');
        self::assertSelectorExists('[data-testid="merge-confirm-submit"]');
        self::assertSelectorTextContains('[data-testid="merge-survivor-summary"]', 'Alice Smith');
        self::assertSelectorTextContains('[data-testid="merge-duplicate-summary"]', 'Alice Smith');
    }

    #[Test]
    #[TestDox('GET the confirm dialog 404s when the duplicate member is unknown.')]
    public function confirm_dialog_404s_for_unknown_duplicate(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);
        $this->seedSurvivor();

        $client->request('GET', $this->confirmUrlFor(self::DUPLICATE_MEMBER_ID, self::DUPLICATE_HOUSEHOLD_ID));

        self::assertResponseStatusCodeSame(404);
    }

    #[Test]
    #[TestDox('POST merges cross-household: redirects, gap-fills contact, and rejects a re-merge.')]
    public function post_merges_cross_household_and_is_not_repeatable(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);
        $this->seedSurvivor();
        $this->seedDuplicate(PhoneNumber::of('5559999'));

        $crawler = $client->request('GET', $this->confirmUrl());
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Merge')->form([
            'merge_members[acknowledged]' => '1',
        ]);
        $client->submit($form);

        self::assertResponseStatusCodeSame(200);
        $hxRedirect = (string) $client->getResponse()->headers->get('HX-Redirect');
        self::assertSame(
            sprintf('/admin/users/%s/%s', self::SURVIVOR_HOUSEHOLD_ID, self::SURVIVOR_MEMBER_ID),
            $hxRedirect,
        );

        // Survivor's blank phone was gap-filled from the duplicate.
        $repo = static::getContainer()->get(Households::class);
        self::assertInstanceOf(Households::class, $repo);
        $survivorHousehold = $repo->findById(HouseholdId::fromString(self::SURVIVOR_HOUSEHOLD_ID));
        $survivor = $survivorHousehold->members()[0];
        self::assertNotNull($survivor->contact()->phone);
        self::assertSame('5559999', $survivor->contact()->phone->value);

        // Duplicate's detail page shows the merged banner and is read-only.
        $client->request('GET', sprintf(
            '/admin/users/%s/%s',
            self::DUPLICATE_HOUSEHOLD_ID,
            self::DUPLICATE_MEMBER_ID,
        ));
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-testid="merged-banner"]');
        self::assertSelectorExists('[data-testid="badge-merged"]');

        // The Users list hides the duplicate by default and shows it with includeMerged=1.
        $client->request('GET', '/admin/users');
        self::assertSelectorNotExists(sprintf('[data-testid="member-row-%s"]', self::DUPLICATE_MEMBER_ID));
        $client->request('GET', '/admin/users?includeMerged=1');
        self::assertSelectorExists(sprintf('[data-testid="member-row-%s"]', self::DUPLICATE_MEMBER_ID));

        // A second merge attempt against the same (now-merged) duplicate is
        // rejected with 422 rather than silently repeating.
        $crawler = $client->request('GET', $this->confirmUrl());
        self::assertResponseIsSuccessful();
        $form = $crawler->selectButton('Merge')->form([
            'merge_members[acknowledged]' => '1',
        ]);
        $client->submit($form);
        self::assertResponseStatusCodeSame(422);
    }

    #[Test]
    #[TestDox('POST self-merge (duplicate id equals the survivor) returns 422 with a form error.')]
    public function post_self_merge_returns_422(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);
        $this->seedSurvivor();

        $selfConfirmUrl = $this->confirmUrlFor(self::SURVIVOR_MEMBER_ID, self::SURVIVOR_HOUSEHOLD_ID);
        $crawler = $client->request('GET', $selfConfirmUrl);
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Merge')->form([
            'merge_members[acknowledged]' => '1',
        ]);
        $client->submit($form);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorExists('[data-testid="merge-form-error"]');
    }

    private function confirmUrl(): string
    {
        return $this->confirmUrlFor(self::DUPLICATE_MEMBER_ID, self::DUPLICATE_HOUSEHOLD_ID);
    }

    private function confirmUrlFor(string $duplicateMemberId, string $duplicateHouseholdId): string
    {
        return sprintf(
            '/admin/users/%s/%s/merge/confirm?duplicateMemberId=%s&duplicateHouseholdId=%s',
            self::SURVIVOR_HOUSEHOLD_ID,
            self::SURVIVOR_MEMBER_ID,
            $duplicateMemberId,
            $duplicateHouseholdId,
        );
    }

    private function seedSurvivor(): void
    {
        $repo = static::getContainer()->get(Households::class);
        self::assertInstanceOf(Households::class, $repo);

        $this->seedSmithHousehold(
            $repo,
            HouseholdId::fromString(self::SURVIVOR_HOUSEHOLD_ID),
            MemberId::fromString(self::SURVIVOR_MEMBER_ID),
            MemberCode::of(self::SURVIVOR_MEMBER_CODE),
            self::DOB,
            $this->clock,
        );
    }

    private function seedDuplicate(?PhoneNumber $phone): void
    {
        $repo = static::getContainer()->get(Households::class);
        self::assertInstanceOf(Households::class, $repo);

        $household = Household::register(
            HouseholdId::fromString(self::DUPLICATE_HOUSEHOLD_ID),
            HouseholdName::of('Smith Family'),
            Address::of('100 Main St', 'Apt 2B', 'Seattle', 'WA', '98101', 'US'),
            MemberId::fromString(self::DUPLICATE_MEMBER_ID),
            MemberCode::of(self::DUPLICATE_MEMBER_CODE),
            MemberProfile::of(
                PersonName::of('Alice', 'Smith'),
                DateOfBirth::of(new DateTimeImmutable(self::DOB), $this->clock),
                Gender::Female,
            ),
            MemberContact::of(EmailAddress::of('alice.dup@example.com'), $phone),
            ResidencyStatus::Resident,
            $this->clock,
        );

        $repo->save($household);
    }
}
