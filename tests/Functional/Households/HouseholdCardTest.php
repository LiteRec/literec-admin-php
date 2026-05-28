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
 * Drives the Household card (LRA-42) plus its lower-card partial endpoint
 * end-to-end through the real container, the Doctrine read model, and a
 * freshly-seeded Households aggregate. DAMA rolls back the seeded rows at
 * teardown so cases stay isolated.
 */
#[Large]
#[Group('database')]
final class HouseholdCardTest extends WebTestCase
{
    use SignsInUsers;

    /** Reused literals (SonarCloud php:S1192). */
    private const string ROUTE_MEMBER = '/admin/users/%s/%s';
    private const string SEL_MEMBER_ROW = '[data-testid="household-member-row-%s"]';


    private const string TEST_USERNAME = 'household_card_e2e';

    private const string HOUSEHOLD_A    = '019571bf-5d53-7000-b500-00000000aa01';
    private const string A_PRIMARY_ID   = '019571bf-5d53-7000-b500-00000000aa02';
    private const string A_SECOND_ID    = '019571bf-5d53-7000-b500-00000000aa03';
    private const string A_THIRD_ID     = '019571bf-5d53-7000-b500-00000000aa04';
    private const string A_PRIMARY_CODE = 'M000210';
    private const string A_SECOND_CODE  = 'M000211';
    private const string A_THIRD_CODE   = 'M000212';

    private const string UNKNOWN_MEMBER_ID = '019571bf-5d53-7000-b500-0000000000fe';

    private MockClock $clock;

    protected function setUp(): void
    {
        $this->clock = new MockClock(new DateTimeImmutable('2026-05-24 12:00:00'));
    }

    #[Test]
    #[TestDox('GET member detail renders every household member as a clickable row in the Household card.')]
    public function get_member_detail_renders_all_household_members_in_household_card(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);
        $this->seedHouseholdA();

        $client->request('GET', sprintf(self::ROUTE_MEMBER, self::HOUSEHOLD_A, self::A_PRIMARY_ID));

        self::assertResponseIsSuccessful();
        self::assertSelectorExists(sprintf(self::SEL_MEMBER_ROW, self::A_PRIMARY_ID));
        self::assertSelectorExists(sprintf(self::SEL_MEMBER_ROW, self::A_SECOND_ID));
        self::assertSelectorExists(sprintf(self::SEL_MEMBER_ROW, self::A_THIRD_ID));
    }

    #[Test]
    #[TestDox('The active member row is marked with aria-current="true"; other rows are not.')]
    public function active_member_row_is_marked_aria_current(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);
        $this->seedHouseholdA();

        $crawler = $client->request(
            'GET',
            sprintf(self::ROUTE_MEMBER, self::HOUSEHOLD_A, self::A_SECOND_ID),
        );
        self::assertResponseIsSuccessful();

        $activeRow = $crawler->filter(
            sprintf(self::SEL_MEMBER_ROW, self::A_SECOND_ID),
        );
        self::assertSame(
            'true',
            $activeRow->attr('aria-current'),
            'Active member row should carry aria-current="true".',
        );

        foreach ([self::A_PRIMARY_ID, self::A_THIRD_ID] as $inactiveRowId) {
            $other = $crawler->filter(
                sprintf(self::SEL_MEMBER_ROW, $inactiveRowId),
            );
            self::assertNull(
                $other->attr('aria-current'),
                sprintf('Non-active row %s should not carry aria-current.', $inactiveRowId),
            );
        }
    }

    #[Test]
    #[TestDox('GET /admin/users/{h}/{m}/_lower-cards returns a fragment containing only the three lower cards.')]
    public function get_lower_cards_partial_returns_only_the_three_lower_cards(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);
        $this->seedHouseholdA();

        $client->request(
            'GET',
            sprintf('/admin/users/%s/%s/_lower-cards', self::HOUSEHOLD_A, self::A_PRIMARY_ID),
        );

        self::assertResponseIsSuccessful();
        $body = (string) $client->getResponse()->getContent();

        // Fragment, not a full page: no <!DOCTYPE> or <html> wrapper.
        self::assertStringNotContainsStringIgnoringCase('<!doctype', $body);
        self::assertStringNotContainsStringIgnoringCase('<html', $body);

        self::assertSelectorExists('[data-testid="card-profile"]');
        self::assertSelectorExists('[data-testid="card-address"]');
        self::assertSelectorExists('[data-testid="card-history"]');
        self::assertSelectorNotExists('[data-testid="card-household"]');
    }

    #[Test]
    #[TestDox('GET lower-cards partial returns 404 when the member id is unknown.')]
    public function get_lower_cards_for_unknown_member_returns_404(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);
        $this->seedHouseholdA();

        $client->request(
            'GET',
            sprintf('/admin/users/%s/%s/_lower-cards', self::HOUSEHOLD_A, self::UNKNOWN_MEMBER_ID),
        );

        self::assertResponseStatusCodeSame(404);
    }

    #[Test]
    #[TestDox('The Household card Add Member button targets the existing households_member_new_form route.')]
    public function add_member_button_links_to_existing_dialog_route(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);
        $this->seedHouseholdA();

        $crawler = $client->request(
            'GET',
            sprintf(self::ROUTE_MEMBER, self::HOUSEHOLD_A, self::A_PRIMARY_ID),
        );
        self::assertResponseIsSuccessful();

        $button = $crawler->filter('[data-testid="add-member"]');
        self::assertGreaterThan(0, $button->count(), 'Add Member button should be rendered.');

        $expectedHref = sprintf('/admin/users/%s/members/new', self::HOUSEHOLD_A);
        self::assertSame(
            $expectedHref,
            $button->attr('hx-get'),
            'Add Member button hx-get should point at the LRA-40 dialog route.',
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

        $household->addMember(
            MemberId::fromString(self::A_THIRD_ID),
            MemberCode::of(self::A_THIRD_CODE),
            PersonName::of('Eli', 'Underwood'),
            DateOfBirth::of(new DateTimeImmutable('2005-07-12'), $this->clock),
            Gender::Other,
            null,
            null,
            ResidencyStatus::Resident,
            false,
            $this->clock,
        );

        $repo->save($household);
    }
}
