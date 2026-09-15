<?php

declare(strict_types=1);

namespace App\Tests\Unit\Households\Application\Event;

use App\Households\Application\Event\ReleaseMemberPhotoFileHandler;
use App\Households\Domain\Event\MemberPhotoReleased;
use App\Households\Domain\Exception\MemberPhotoStorageFailed;
use App\Households\Domain\MemberPhotoStorage;
use App\Households\Domain\ValueObject\ImageFormat;
use App\Households\Domain\ValueObject\MemberId;
use App\Households\Infrastructure\Storage\InMemoryMemberPhotoStorage;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

#[Small]
final class ReleaseMemberPhotoFileHandlerTest extends TestCase
{
    private const string MEMBER_ID = '019571bf-5d55-7000-b500-000000000e01';

    #[Test]
    #[TestDox('Removes the storage file for the released storage key.')]
    public function removes_the_released_file(): void
    {
        $storage = new InMemoryMemberPhotoStorage();
        $sourcePath = tempnam(sys_get_temp_dir(), 'release-member-photo-');
        self::assertNotFalse($sourcePath);
        file_put_contents($sourcePath, 'fake jpeg bytes');
        $key = $storage->store(MemberId::fromString(self::MEMBER_ID), ImageFormat::Jpeg, $sourcePath);

        $handler = new ReleaseMemberPhotoFileHandler($storage, new NullLogger());
        $handler(new MemberPhotoReleased($key, new DateTimeImmutable('2026-05-24 12:00:00')));

        $this->expectException(MemberPhotoStorageFailed::class);
        $storage->readable($key);
    }

    #[Test]
    #[TestDox('Removing an already-absent key is a no-op, not an error.')]
    public function removing_absent_key_is_a_no_op(): void
    {
        $storage = new InMemoryMemberPhotoStorage();
        $handler = new ReleaseMemberPhotoFileHandler($storage, new NullLogger());

        $handler(new MemberPhotoReleased(
            self::MEMBER_ID . '/0000000000000000000000000000ff.png',
            new DateTimeImmutable('2026-05-24 12:00:00'),
        ));

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    #[TestDox('A storage failure while removing the file is logged, not thrown, since the change already committed.')]
    public function storage_failure_is_logged_and_swallowed(): void
    {
        $storage = new class implements MemberPhotoStorage {
            public function store(MemberId $memberId, ImageFormat $format, string $sourcePath): string
            {
                throw new \LogicException('Not exercised by this test.');
            }

            public function readable(string $storageKey): \SplFileInfo
            {
                throw new \LogicException('Not exercised by this test.');
            }

            public function remove(string $storageKey): void
            {
                throw MemberPhotoStorageFailed::removing($storageKey);
            }
        };

        $handler = new ReleaseMemberPhotoFileHandler($storage, new NullLogger());

        $handler(new MemberPhotoReleased('some/key.jpg', new DateTimeImmutable('2026-05-24 12:00:00')));

        $this->expectNotToPerformAssertions();
    }
}
