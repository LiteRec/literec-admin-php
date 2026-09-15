<?php

declare(strict_types=1);

namespace App\Tests\Support\Trait;

use App\Households\Domain\Exception\MemberPhotoStorageFailed;
use App\Households\Domain\MemberPhotoStorage;
use App\Households\Domain\ValueObject\ImageFormat;
use App\Households\Domain\ValueObject\MemberId;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * Shared behavioral contract for any {@see MemberPhotoStorage} adapter.
 * Concrete test classes (`InMemoryMemberPhotoStorageContractTest`,
 * `LocalMemberPhotoStorageContractTest`) use this trait so the two
 * implementations cannot drift apart.
 */
trait MemberPhotoStorageContractCases
{
    private const string MEMBER_ID = '019571bf-5d55-7000-b500-000000000e01';

    abstract protected function storage(): MemberPhotoStorage;

    #[Test]
    #[TestDox('store(): returns a well-formed key under the member id and the stored file is readable.')]
    public function store_returns_a_well_formed_key_and_a_readable_file(): void
    {
        $sourcePath = $this->writeSourceFile("\x89PNG fake bytes");

        $key = $this->storage()->store(MemberId::fromString(self::MEMBER_ID), ImageFormat::Png, $sourcePath);

        self::assertMatchesRegularExpression(
            '#^' . preg_quote(self::MEMBER_ID, '#') . '/[0-9a-f]{32}\.png$#',
            $key,
        );

        $readable = $this->storage()->readable($key);
        self::assertFileExists($readable->getPathname());
        self::assertSame("\x89PNG fake bytes", file_get_contents($readable->getPathname()));
    }

    #[Test]
    #[TestDox('store(): consecutive calls allocate distinct keys.')]
    public function store_allocates_distinct_keys_on_consecutive_calls(): void
    {
        $sourcePath = $this->writeSourceFile('one image');

        $first = $this->storage()->store(MemberId::fromString(self::MEMBER_ID), ImageFormat::Jpeg, $sourcePath);
        $second = $this->storage()->store(MemberId::fromString(self::MEMBER_ID), ImageFormat::Jpeg, $sourcePath);

        self::assertNotSame($first, $second);
    }

    #[Test]
    #[TestDox('remove(): deletes the stored file so a subsequent readable() throws.')]
    public function remove_deletes_the_stored_file(): void
    {
        $sourcePath = $this->writeSourceFile('to be removed');
        $key = $this->storage()->store(MemberId::fromString(self::MEMBER_ID), ImageFormat::Webp, $sourcePath);

        $this->storage()->remove($key);

        $this->expectException(MemberPhotoStorageFailed::class);
        $this->storage()->readable($key);
    }

    #[Test]
    #[TestDox('remove(): removing an already-absent key is a no-op, not an error.')]
    public function remove_is_idempotent(): void
    {
        $this->storage()->remove(self::MEMBER_ID . '/0000000000000000000000000000ff.png');

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    #[TestDox('readable(): throws MemberPhotoStorageFailed for a key that was never stored.')]
    public function readable_throws_for_unknown_key(): void
    {
        $this->expectException(MemberPhotoStorageFailed::class);

        $this->storage()->readable(self::MEMBER_ID . '/0000000000000000000000000000ff.png');
    }

    #[Test]
    #[TestDox('readable(): throws MemberPhotoStorageFailed for a path-traversal key instead of escaping the root.')]
    public function readable_rejects_path_traversal_keys(): void
    {
        $this->expectException(MemberPhotoStorageFailed::class);

        $this->storage()->readable('../../etc/passwd');
    }

    private function writeSourceFile(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'member-photo-source-');
        self::assertNotFalse($path);
        file_put_contents($path, $contents);

        return $path;
    }
}
