<?php

declare(strict_types=1);

namespace App\Tests\Unit\Households\Application\Command;

use App\Households\Application\Command\AttachMemberPhoto;
use App\Households\Application\Command\AttachMemberPhotoHandler;
use App\Households\Domain\Event\MemberPhotoAttached;
use App\Households\Domain\Event\MemberPhotoReleased;
use App\Households\Domain\Exception\MemberNotFound;
use App\Households\Domain\Exception\UnsupportedImageFormat;
use App\Households\Domain\ValueObject\HouseholdId;
use App\Households\Domain\ValueObject\MemberCode;
use App\Households\Domain\ValueObject\MemberId;
use App\Households\Infrastructure\Persistence\InMemory\InMemoryHouseholds;
use App\Households\Infrastructure\Storage\InMemoryMemberPhotoStorage;
use App\Tests\Support\Fake\RecordingMessageBus;
use App\Tests\Support\Trait\SeedsAliceSmithHousehold;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[Small]
final class AttachMemberPhotoHandlerTest extends TestCase
{
    use SeedsAliceSmithHousehold;

    private const string HOUSEHOLD_ID = '019571bf-5d54-7000-b500-000000000d01';
    private const string PRIMARY_ID   = '019571bf-5d54-7000-b500-000000000d02';
    private const string PRIMARY_CODE = 'M000400';
    private const string UNKNOWN_ID   = '019571bf-5d54-7000-b500-0000000000fe';

    private MockClock $clock;
    private InMemoryHouseholds $households;
    private InMemoryMemberPhotoStorage $storage;
    private RecordingMessageBus $eventBus;
    private AttachMemberPhotoHandler $handler;
    private string $sourcePath;

    protected function setUp(): void
    {
        $this->clock = new MockClock(new DateTimeImmutable('2026-05-24 12:00:00'));
        $this->households = new InMemoryHouseholds();
        $seed = $this->seedAliceSmithHousehold(
            HouseholdId::fromString(self::HOUSEHOLD_ID),
            MemberId::fromString(self::PRIMARY_ID),
            MemberCode::of(self::PRIMARY_CODE),
            $this->clock,
        );
        $seed->releaseEvents();
        $this->households->save($seed);

        $this->storage = new InMemoryMemberPhotoStorage();
        $this->eventBus = new RecordingMessageBus();
        $this->handler = new AttachMemberPhotoHandler(
            $this->households,
            $this->storage,
            $this->clock,
            $this->eventBus,
        );

        $this->sourcePath = tempnam(sys_get_temp_dir(), 'attach-member-photo-');
        self::assertNotFalse($this->sourcePath);
        file_put_contents($this->sourcePath, 'fake jpeg bytes');
    }

    #[Test]
    #[TestDox('Stores the file, attaches the photo, and publishes MemberPhotoAttached.')]
    public function happy_path_stores_and_attaches_photo(): void
    {
        ($this->handler)(new AttachMemberPhoto(
            householdId: self::HOUSEHOLD_ID,
            memberId: self::PRIMARY_ID,
            sourcePath: $this->sourcePath,
            mimeType: 'image/jpeg',
        ));

        $stored = $this->households->findById(HouseholdId::fromString(self::HOUSEHOLD_ID));
        $member = $this->memberById($stored, self::PRIMARY_ID);
        self::assertNotNull($member->photo());
        self::assertStringStartsWith(self::PRIMARY_ID . '/', $member->photo()->storageKey);
        self::assertStringEndsWith('.jpg', $member->photo()->storageKey);

        $messages = $this->eventBus->dispatchedMessages();
        self::assertCount(1, $messages);
        self::assertInstanceOf(MemberPhotoAttached::class, $messages[0]);
    }

    #[Test]
    #[TestDox('Replacing an existing photo also publishes MemberPhotoReleased for the superseded key.')]
    public function replacing_photo_also_publishes_released_event(): void
    {
        ($this->handler)(new AttachMemberPhoto(
            householdId: self::HOUSEHOLD_ID,
            memberId: self::PRIMARY_ID,
            sourcePath: $this->sourcePath,
            mimeType: 'image/jpeg',
        ));

        $secondSourcePath = tempnam(sys_get_temp_dir(), 'attach-member-photo-2-');
        self::assertNotFalse($secondSourcePath);
        file_put_contents($secondSourcePath, 'fake png bytes');

        ($this->handler)(new AttachMemberPhoto(
            householdId: self::HOUSEHOLD_ID,
            memberId: self::PRIMARY_ID,
            sourcePath: $secondSourcePath,
            mimeType: 'image/png',
        ));

        // RecordingMessageBus is a non-draining sink: the first attach's
        // MemberPhotoAttached (index 0) is still present alongside the
        // second attach's MemberPhotoAttached + MemberPhotoReleased.
        $messages = $this->eventBus->dispatchedMessages();
        self::assertCount(3, $messages);
        self::assertInstanceOf(MemberPhotoAttached::class, $messages[1]);
        self::assertInstanceOf(MemberPhotoReleased::class, $messages[2]);
    }

    #[Test]
    #[TestDox('Throws UnsupportedImageFormat for a non-image MIME type.')]
    public function rejects_unsupported_mime_type(): void
    {
        $this->expectException(UnsupportedImageFormat::class);

        ($this->handler)(new AttachMemberPhoto(
            householdId: self::HOUSEHOLD_ID,
            memberId: self::PRIMARY_ID,
            sourcePath: $this->sourcePath,
            mimeType: 'text/plain',
        ));
    }

    #[Test]
    #[TestDox('Throws MemberNotFound when the memberId does not exist in the household.')]
    public function rejects_unknown_member(): void
    {
        $this->expectException(MemberNotFound::class);

        ($this->handler)(new AttachMemberPhoto(
            householdId: self::HOUSEHOLD_ID,
            memberId: self::UNKNOWN_ID,
            sourcePath: $this->sourcePath,
            mimeType: 'image/jpeg',
        ));
    }
}
