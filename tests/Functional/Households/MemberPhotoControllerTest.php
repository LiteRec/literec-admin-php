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
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Drives the Profile card's photo upload/remove flow (LRA-207) end-to-end
 * through the real container, the in-memory MemberPhotoStorage test
 * binding (see `when@test` in config/services.yaml), and a freshly-seeded
 * Households aggregate. DAMA rolls back the seeded rows at teardown so
 * cases stay isolated.
 */
#[Large]
#[Group('database')]
final class MemberPhotoControllerTest extends WebTestCase
{
    use SignsInUsers;

    private const string TEST_USERNAME = 'member_photo_e2e';
    private const string DOB = '1990-01-01';

    private const string HOUSEHOLD_A    = '019571bf-5d55-7000-b500-00000000ba01';
    private const string A_PRIMARY_ID   = '019571bf-5d55-7000-b500-00000000ba02';
    private const string A_PRIMARY_CODE = 'M000610';

    /**
     * A minimal well-formed 1x1 transparent PNG, base64-encoded. No
     * gd/imagick is installed in this runtime, so tests build valid image
     * bytes from a literal rather than generating them.
     */
    private const string ONE_PIXEL_PNG_BASE64
        = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';

    private MockClock $clock;

    protected function setUp(): void
    {
        $this->clock = new MockClock(new DateTimeImmutable('2026-05-24 12:00:00'));
    }

    #[Test]
    #[TestDox('A member with no uploaded photo renders the initials fallback, not an image.')]
    public function member_with_no_photo_renders_initials(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);
        $this->seedHouseholdA();

        $client->request('GET', sprintf('/admin/users/%s/%s', self::HOUSEHOLD_A, self::A_PRIMARY_ID));

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-testid="member-avatar-initials"]');
        self::assertSelectorNotExists('[data-testid="member-photo-img"]');
    }

    #[Test]
    #[TestDox('A valid upload swaps the Profile card to show the photo and OOB-updates the header avatar.')]
    public function valid_upload_swaps_to_photo_and_updates_header(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);
        $this->seedHouseholdA();

        $this->uploadPhoto($client, self::HOUSEHOLD_A, self::A_PRIMARY_ID, $this->pngUploadedFile());

        self::assertResponseIsSuccessful();
        $body = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('data-testid="member-photo-img"', $body);
        self::assertStringContainsString('id="member-avatar"', $body);
        self::assertStringContainsString('hx-swap-oob="true"', $body);
        self::assertSame('photoSaved', $client->getResponse()->headers->get('HX-Trigger'));
    }

    #[Test]
    #[TestDox('GET the uploaded photo returns 200 with the stored Content-Type.')]
    public function get_uploaded_photo_returns_200_with_content_type(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);
        $this->seedHouseholdA();
        $this->uploadPhoto($client, self::HOUSEHOLD_A, self::A_PRIMARY_ID, $this->pngUploadedFile());

        $version = $this->currentPhotoVersion($client);

        $client->request(
            'GET',
            sprintf('/admin/users/%s/%s/photo/%s', self::HOUSEHOLD_A, self::A_PRIMARY_ID, $version),
        );

        self::assertResponseIsSuccessful();
        self::assertSame('image/png', $client->getResponse()->headers->get('Content-Type'));
    }

    #[Test]
    #[TestDox('GET a stale/wrong photo version returns 404.')]
    public function get_stale_photo_version_returns_404(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);
        $this->seedHouseholdA();
        $this->uploadPhoto($client, self::HOUSEHOLD_A, self::A_PRIMARY_ID, $this->pngUploadedFile());

        $client->request(
            'GET',
            sprintf(
                '/admin/users/%s/%s/photo/%s',
                self::HOUSEHOLD_A,
                self::A_PRIMARY_ID,
                str_repeat('0', 31) . 'f',
            ),
        );

        self::assertResponseStatusCodeSame(404);
    }

    #[Test]
    #[TestDox('Uploading a text/plain file returns 422 with an inline error, never a 500.')]
    public function upload_wrong_type_returns_422_not_500(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);
        $this->seedHouseholdA();

        $path = tempnam(sys_get_temp_dir(), 'not-an-image-');
        self::assertNotFalse($path);
        file_put_contents($path, 'just some text, not an image');
        $file = new UploadedFile($path, 'notes.txt', 'text/plain', null, true);

        $this->uploadPhoto($client, self::HOUSEHOLD_A, self::A_PRIMARY_ID, $file);

        self::assertResponseStatusCodeSame(422);
        $body = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('data-testid="photo-form-error"', $body);
        self::assertSelectorExists('[data-testid="member-avatar-initials"]');
    }

    #[Test]
    #[TestDox('Uploading a file over 2MiB returns 422.')]
    public function upload_over_size_limit_returns_422(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);
        $this->seedHouseholdA();

        $path = tempnam(sys_get_temp_dir(), 'too-big-');
        self::assertNotFalse($path);
        file_put_contents($path, str_repeat('a', 3 * 1024 * 1024));
        $file = new UploadedFile($path, 'huge.png', 'image/png', null, true);

        $this->uploadPhoto($client, self::HOUSEHOLD_A, self::A_PRIMARY_ID, $file);

        self::assertResponseStatusCodeSame(422);
    }

    #[Test]
    #[TestDox('Remove restores the initials fallback and OOB-updates the header avatar.')]
    public function remove_restores_initials_fallback(): void
    {
        $client = static::createClient();
        $this->signInUser($client, self::TEST_USERNAME, self::TEST_PASSWORD);
        $this->seedHouseholdA();
        $this->uploadPhoto($client, self::HOUSEHOLD_A, self::A_PRIMARY_ID, $this->pngUploadedFile());

        $token = $this->extractTokenValue($client, 'remove_member_photo');
        $client->request(
            'POST',
            sprintf('/admin/users/%s/%s/photo/remove', self::HOUSEHOLD_A, self::A_PRIMARY_ID),
            ['remove_member_photo' => ['_token' => $token]],
        );

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-testid="member-avatar-initials"]');
        self::assertSelectorNotExists('[data-testid="member-photo-img"]');
        self::assertSame('photoSaved', $client->getResponse()->headers->get('HX-Trigger'));
    }

    #[Test]
    #[TestDox('The photo route requires authentication: an anonymous GET redirects to /login.')]
    public function photo_route_requires_login(): void
    {
        $client = static::createClient();

        $client->request(
            'GET',
            sprintf(
                '/admin/users/%s/%s/photo/%s',
                self::HOUSEHOLD_A,
                self::A_PRIMARY_ID,
                str_repeat('1', 32),
            ),
        );

        self::assertResponseRedirects('/login');
    }

    private function pngUploadedFile(): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'member-photo-');
        self::assertNotFalse($path);
        file_put_contents($path, base64_decode(self::ONE_PIXEL_PNG_BASE64, true));

        return new UploadedFile($path, 'avatar.png', 'image/png', null, true);
    }

    private function uploadPhoto(
        KernelBrowser $client,
        string $householdId,
        string $memberId,
        UploadedFile $file,
    ): void {
        $token = $this->extractTokenValue($client, 'upload_member_photo', $householdId, $memberId);

        $client->request(
            'POST',
            sprintf('/admin/users/%s/%s/photo', $householdId, $memberId),
            ['upload_member_photo' => ['_token' => $token]],
            ['upload_member_photo' => ['photo' => $file]],
        );
    }

    /**
     * Scrapes the CSRF token value for $formName from the member detail
     * page's rendered HTML — both the upload and remove forms are only
     * ever rendered inline on the Profile card (no dedicated "edit form"
     * GET endpoint the way the profile-identity flow has one).
     */
    private function extractTokenValue(
        KernelBrowser $client,
        string $formName,
        ?string $householdId = null,
        ?string $memberId = null,
    ): string {
        $crawler = $client->request(
            'GET',
            sprintf(
                '/admin/users/%s/%s',
                $householdId ?? self::HOUSEHOLD_A,
                $memberId ?? self::A_PRIMARY_ID,
            ),
        );
        self::assertResponseIsSuccessful();

        $tokenField = $crawler->filter(sprintf('input[name="%s[_token]"]', $formName));
        self::assertGreaterThan(
            0,
            $tokenField->count(),
            sprintf('CSRF token field for %s was not rendered.', $formName),
        );

        return (string) $tokenField->attr('value');
    }

    private function currentPhotoVersion(KernelBrowser $client): string
    {
        $crawler = $client->request(
            'GET',
            sprintf('/admin/users/%s/%s', self::HOUSEHOLD_A, self::A_PRIMARY_ID),
        );
        self::assertResponseIsSuccessful();

        $img = $crawler->filter('[data-testid="member-photo-img"]')->first();
        self::assertGreaterThan(0, $img->count(), 'Expected a rendered photo image after upload.');

        $src = (string) $img->attr('src');
        $matches = [];
        preg_match('#/photo/([0-9a-f]{32})$#', $src, $matches);

        if (!isset($matches[1])) {
            self::fail('Could not extract photo version from src.');
        }

        return $matches[1];
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
            DateOfBirth::of(new DateTimeImmutable(self::DOB), $this->clock),
            Gender::Female,
            EmailAddress::of('alice@example.com'),
            null,
            ResidencyStatus::Resident,
            $this->clock,
        );

        $repo->save($household);
    }
}
