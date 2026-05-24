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
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\MockClock;

/**
 * Drives the Address & Residency card (LRA-44) end-to-end through the
 * real container, Doctrine repositories, command and event buses, and the
 * persistence write into `household_residency_history`.
 *
 * DAMA rolls back rows at teardown so cases stay isolated.
 */
#[Large]
#[Group('database')]
final class AddressResidencyCardTest extends WebTestCase
{
    use SignsInUsers;

    private const string TEST_USERNAME = 'address_card_e2e';
    private const string TEST_PASSWORD = 'CorrectHorseBattery!';

    private const string HOUSEHOLD_US        = '019571bf-5d55-7000-b500-00000000cc01';
    private const string US_PRIMARY_ID       = '019571bf-5d55-7000-b500-00000000cc02';
    private const string US_PRIMARY_CODE     = 'M000610';

    private const string HOUSEHOLD_CA        = '019571bf-5d55-7000-b500-00000000cc11';
    private const string CA_PRIMARY_ID       = '019571bf-5d55-7000-b500-00000000cc12';
    private const string CA_PRIMARY_CODE     = 'M000611';

    private const string UNKNOWN_MEMBER_ID   = '019571bf-5d55-7000-b500-0000000000fe';

    private MockClock $clock;

    protected function setUp(): void
    {
        $this->clock = new MockClock(new DateTimeImmutable('2026-05-24 12:00:00'));
    }

    #[Test]
    #[TestDox('Renders both Address and Residency sub-cards in read mode with the seeded values.')]
    public function renders_address_and_residency_sub_cards_in_read_mode(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);
        $this->seedUsHousehold();

        $client->request('GET', sprintf('/admin/users/%s/%s', self::HOUSEHOLD_US, self::US_PRIMARY_ID));

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-testid="address-sub-card-body"]');
        self::assertSelectorExists('[data-testid="residency-sub-card-body"]');
        self::assertSelectorExists('[data-testid="address-block"]');
        self::assertSelectorTextContains('[data-testid="address-street"]', '100 Main St');
        self::assertSelectorTextContains('[data-testid="address-city-state-zip"]', 'Seattle, WA 98101');
        self::assertSelectorTextContains('[data-testid="residency-status-badge"]', 'Resident');
    }

    #[Test]
    #[TestDox('Renders a non-US address in the single-line fallback format.')]
    public function renders_non_us_address_in_single_line_fallback(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);
        $this->seedCaHousehold();

        $client->request('GET', sprintf('/admin/users/%s/%s', self::HOUSEHOLD_CA, self::CA_PRIMARY_ID));

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-testid="address-single-line"]');
        self::assertSelectorTextContains(
            '[data-testid="address-single-line"]',
            '100 Maple Ave, Toronto, ON K1A 0B1, CA',
        );
    }

    #[Test]
    #[TestDox('Editing the address with valid values swaps the sub-card back to read mode and persists.')]
    public function edit_address_then_save_swaps_back_to_read_mode_with_new_values(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);
        $this->seedUsHousehold();

        $token = $this->csrfTokenFromAddressEditForm($client);

        $client->request(
            'POST',
            sprintf('/admin/users/%s/address', self::HOUSEHOLD_US),
            [
                'member_context_id' => self::US_PRIMARY_ID,
                'update_household_address' => [
                    'street' => '200 Oak Ave',
                    'unit' => 'Suite 5',
                    'city' => 'Portland',
                    'state' => 'OR',
                    'postalCode' => '97201',
                    'country' => 'US',
                    '_token' => $token,
                ],
            ],
        );

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-testid="address-sub-card-body"]');
        self::assertSelectorTextContains('[data-testid="address-street"]', '200 Oak Ave');
        self::assertSelectorTextContains('[data-testid="address-city-state-zip"]', 'Portland, OR 97201');

        $repo = static::getContainer()->get(Households::class);
        self::assertInstanceOf(Households::class, $repo);
        $household = $repo->findById(HouseholdId::fromString(self::HOUSEHOLD_US));
        self::assertSame('200 Oak Ave', $household->address()->street);
        self::assertSame('Portland', $household->address()->city);
        self::assertSame('97201', $household->address()->postalCode);
    }

    #[Test]
    #[TestDox('Submitting an invalid US ZIP re-renders the edit partial at 422 with an inline error.')]
    public function edit_address_with_invalid_us_zip_renders_inline_error(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);
        $this->seedUsHousehold();

        $token = $this->csrfTokenFromAddressEditForm($client);

        $client->request(
            'POST',
            sprintf('/admin/users/%s/address', self::HOUSEHOLD_US),
            [
                'member_context_id' => self::US_PRIMARY_ID,
                'update_household_address' => [
                    'street' => '200 Oak Ave',
                    'unit' => '',
                    'city' => 'Portland',
                    'state' => 'OR',
                    'postalCode' => 'ABCDE',
                    'country' => 'US',
                    '_token' => $token,
                ],
            ],
        );

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorExists('#address-sub-card-body form');
        $body = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('postal code is not valid', $body);

        // Aggregate must not have been mutated.
        $repo = static::getContainer()->get(Households::class);
        self::assertInstanceOf(Households::class, $repo);
        $household = $repo->findById(HouseholdId::fromString(self::HOUSEHOLD_US));
        self::assertSame('98101', $household->address()->postalCode);
    }

    #[Test]
    #[TestDox('Changing residency appends one history row per submit and swaps back to read mode.')]
    public function change_residency_appends_history_row_and_swaps_to_read_mode(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);
        $this->seedUsHousehold();

        $token = $this->csrfTokenFromResidencyEditForm($client);

        $client->request(
            'POST',
            sprintf('/admin/users/%s/%s/residency', self::HOUSEHOLD_US, self::US_PRIMARY_ID),
            [
                'change_member_residency' => [
                    'residencyStatusCode' => ResidencyStatus::Member->value,
                    'effectiveFromIso' => '2026-05-10',
                    'reason' => 'Joined membership program',
                    '_token' => $token,
                ],
            ],
        );

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-testid="residency-sub-card-body"]');
        self::assertSelectorTextContains('[data-testid="residency-status-badge"]', 'Member');

        $connection = static::getContainer()->get(Connection::class);
        self::assertInstanceOf(Connection::class, $connection);

        $rows = $connection->fetchAllAssociative(
            'SELECT status, reason FROM household_residency_history '
            . 'WHERE member_id = :m ORDER BY id ASC',
            ['m' => self::US_PRIMARY_ID],
        );
        self::assertCount(1, $rows);
        self::assertSame('MEMBER', $rows[0]['status']);
        self::assertSame('Joined membership program', $rows[0]['reason']);

        // Submit a second change and confirm both rows are present (append-only).
        $token = $this->csrfTokenFromResidencyEditForm($client);

        $client->request(
            'POST',
            sprintf('/admin/users/%s/%s/residency', self::HOUSEHOLD_US, self::US_PRIMARY_ID),
            [
                'change_member_residency' => [
                    'residencyStatusCode' => ResidencyStatus::Staff->value,
                    'effectiveFromIso' => '2026-06-01',
                    'reason' => 'Hired',
                    '_token' => $token,
                ],
            ],
        );

        self::assertResponseIsSuccessful();

        $rows = $connection->fetchAllAssociative(
            'SELECT status, reason FROM household_residency_history '
            . 'WHERE member_id = :m ORDER BY id ASC',
            ['m' => self::US_PRIMARY_ID],
        );
        self::assertCount(2, $rows);
        self::assertSame('MEMBER', $rows[0]['status']);
        self::assertSame('STAFF', $rows[1]['status']);
    }

    #[Test]
    #[TestDox('A residency POST without a CSRF token returns 422 and no history row is appended.')]
    public function change_residency_without_csrf_returns_422(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);
        $this->seedUsHousehold();

        $client->request(
            'POST',
            sprintf('/admin/users/%s/%s/residency', self::HOUSEHOLD_US, self::US_PRIMARY_ID),
            [
                'change_member_residency' => [
                    'residencyStatusCode' => ResidencyStatus::Member->value,
                    'effectiveFromIso' => '2026-05-10',
                    'reason' => '',
                    // No _token.
                ],
            ],
        );

        self::assertResponseStatusCodeSame(422);

        $connection = static::getContainer()->get(Connection::class);
        self::assertInstanceOf(Connection::class, $connection);
        $count = $connection->fetchOne(
            'SELECT COUNT(*) FROM household_residency_history WHERE member_id = :m',
            ['m' => self::US_PRIMARY_ID],
        );
        self::assertTrue(is_numeric($count));
        self::assertSame(0, (int) $count);
    }

    #[Test]
    #[TestDox('A residency POST for an unknown member id returns 404.')]
    public function change_residency_for_unknown_member_returns_404(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);
        $this->seedUsHousehold();

        $token = $this->csrfTokenFromResidencyEditForm($client);

        $client->request(
            'POST',
            sprintf('/admin/users/%s/%s/residency', self::HOUSEHOLD_US, self::UNKNOWN_MEMBER_ID),
            [
                'change_member_residency' => [
                    'residencyStatusCode' => ResidencyStatus::Member->value,
                    'effectiveFromIso' => '2026-05-10',
                    'reason' => '',
                    '_token' => $token,
                ],
            ],
        );

        self::assertResponseStatusCodeSame(404);
    }

    private function csrfTokenFromAddressEditForm(KernelBrowser $client): string
    {
        $crawler = $client->request(
            'GET',
            sprintf('/admin/users/%s/%s/address/edit', self::HOUSEHOLD_US, self::US_PRIMARY_ID),
        );
        self::assertResponseIsSuccessful();

        $tokenField = $crawler->filter('input[name="update_household_address[_token]"]');
        self::assertGreaterThan(0, $tokenField->count(), 'Address CSRF token was not rendered.');

        return (string) $tokenField->attr('value');
    }

    private function csrfTokenFromResidencyEditForm(KernelBrowser $client): string
    {
        $crawler = $client->request(
            'GET',
            sprintf('/admin/users/%s/%s/residency/edit', self::HOUSEHOLD_US, self::US_PRIMARY_ID),
        );
        self::assertResponseIsSuccessful();

        $tokenField = $crawler->filter('input[name="change_member_residency[_token]"]');
        self::assertGreaterThan(0, $tokenField->count(), 'Residency CSRF token was not rendered.');

        return (string) $tokenField->attr('value');
    }

    private function seedUsHousehold(): void
    {
        $repo = static::getContainer()->get(Households::class);
        self::assertInstanceOf(Households::class, $repo);

        $household = Household::register(
            HouseholdId::fromString(self::HOUSEHOLD_US),
            HouseholdName::of('Smith Family'),
            Address::of('100 Main St', 'Apt 2B', 'Seattle', 'WA', '98101', 'US'),
            MemberId::fromString(self::US_PRIMARY_ID),
            MemberCode::of(self::US_PRIMARY_CODE),
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

    private function seedCaHousehold(): void
    {
        $repo = static::getContainer()->get(Households::class);
        self::assertInstanceOf(Households::class, $repo);

        $household = Household::register(
            HouseholdId::fromString(self::HOUSEHOLD_CA),
            HouseholdName::of('Tremblay Family'),
            Address::of('100 Maple Ave', null, 'Toronto', 'ON', 'K1A 0B1', 'CA'),
            MemberId::fromString(self::CA_PRIMARY_ID),
            MemberCode::of(self::CA_PRIMARY_CODE),
            PersonName::of('Jean', 'Tremblay'),
            DateOfBirth::of(new DateTimeImmutable('1988-07-14'), $this->clock),
            Gender::Male,
            EmailAddress::of('jean@example.com'),
            null,
            ResidencyStatus::NonResident,
            $this->clock,
        );

        $repo->save($household);
    }
}
