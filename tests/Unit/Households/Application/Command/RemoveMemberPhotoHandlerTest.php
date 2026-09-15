<?php

declare(strict_types=1);

namespace App\Tests\Unit\Households\Application\Command;

use App\Households\Application\Command\AttachMemberPhoto;
use App\Households\Application\Command\AttachMemberPhotoHandler;
use App\Households\Application\Command\RemoveMemberPhoto;
use App\Households\Application\Command\RemoveMemberPhotoHandler;
use App\Households\Domain\Event\MemberPhotoReleased;
use App\Households\Domain\Event\MemberPhotoRemoved;
use App\Households\Domain\Exception\MemberNotFound;
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
final class RemoveMemberPhotoHandlerTest extends TestCase
{
    use SeedsAliceSmithHousehold;

    private const string HOUSEHOLD_ID = '019571bf-5d54-7000-b500-000000000f01';
    private const string PRIMARY_ID   = '019571bf-5d54-7000-b500-000000000f02';
    private const string PRIMARY_CODE = 'M000500';
    private const string UNKNOWN_ID   = '019571bf-5d54-7000-b500-0000000000fe';

    private MockClock $clock;
    private InMemoryHouseholds $households;
    private InMemoryMemberPhotoStorage $storage;
    private RecordingMessageBus $eventBus;
    private RemoveMemberPhotoHandler $handler;

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
        $this->handler = new RemoveMemberPhotoHandler($this->households, $this->clock, $this->eventBus);
    }

    #[Test]
    #[TestDox('Removes an existing photo and publishes MemberPhotoRemoved + MemberPhotoReleased.')]
    public function happy_path_removes_photo_and_publishes_events(): void
    {
        $this->attachAPhoto();

        ($this->handler)(new RemoveMemberPhoto(self::HOUSEHOLD_ID, self::PRIMARY_ID));

        $stored = $this->households->findById(HouseholdId::fromString(self::HOUSEHOLD_ID));
        self::assertNull($this->memberById($stored, self::PRIMARY_ID)->photo());

        // RecordingMessageBus is a non-draining sink: the attach's
        // MemberPhotoAttached (index 0) is still present alongside the
        // removal's MemberPhotoRemoved + MemberPhotoReleased.
        $messages = $this->eventBus->dispatchedMessages();
        self::assertCount(3, $messages);
        self::assertInstanceOf(MemberPhotoRemoved::class, $messages[1]);
        self::assertInstanceOf(MemberPhotoReleased::class, $messages[2]);
    }

    #[Test]
    #[TestDox('Is a no-op — no events published — when the member has no photo.')]
    public function no_op_when_member_has_no_photo(): void
    {
        ($this->handler)(new RemoveMemberPhoto(self::HOUSEHOLD_ID, self::PRIMARY_ID));

        self::assertSame([], $this->eventBus->dispatchedMessages());
    }

    #[Test]
    #[TestDox('Throws MemberNotFound when the memberId does not exist in the household.')]
    public function rejects_unknown_member(): void
    {
        $this->expectException(MemberNotFound::class);

        ($this->handler)(new RemoveMemberPhoto(self::HOUSEHOLD_ID, self::UNKNOWN_ID));
    }

    private function attachAPhoto(): void
    {
        $sourcePath = tempnam(sys_get_temp_dir(), 'remove-member-photo-');
        self::assertNotFalse($sourcePath);
        file_put_contents($sourcePath, 'fake jpeg bytes');

        $attachHandler = new AttachMemberPhotoHandler(
            $this->households,
            $this->storage,
            $this->clock,
            $this->eventBus,
        );
        $attachHandler(new AttachMemberPhoto(
            householdId: self::HOUSEHOLD_ID,
            memberId: self::PRIMARY_ID,
            sourcePath: $sourcePath,
            mimeType: 'image/jpeg',
        ));
    }
}
