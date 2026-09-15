<?php

declare(strict_types=1);

namespace App\Tests\Unit\Households\Domain\ValueObject;

use App\Households\Domain\Exception\InvalidProfilePhoto;
use App\Households\Domain\ValueObject\ImageFormat;
use App\Households\Domain\ValueObject\ProfilePhoto;
use DateTimeImmutable;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[Small]
final class ProfilePhotoTest extends TestCase
{
    private const string MEMBER_ID = '019571bf-5d55-7000-b500-000000000e01';

    #[Test]
    #[TestDox('::of() constructs from a well-formed storage key.')]
    public function constructs_from_well_formed_key(): void
    {
        $uploadedAt = new DateTimeImmutable('2026-05-24 12:00:00');
        $key = self::MEMBER_ID . '/aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa.jpg';

        $photo = ProfilePhoto::of($key, ImageFormat::Jpeg, $uploadedAt);

        self::assertSame($key, $photo->storageKey);
        self::assertSame(ImageFormat::Jpeg, $photo->format);
        self::assertSame($uploadedAt, $photo->uploadedAt);
    }

    #[Test]
    #[TestDox('::of() rejects an empty storage key with InvalidProfilePhoto.')]
    public function rejects_empty_key(): void
    {
        $this->expectException(InvalidProfilePhoto::class);

        ProfilePhoto::of('', ImageFormat::Jpeg, new DateTimeImmutable());
    }

    /**
     * @return Generator<string, array{key: string}>
     */
    public static function illegalKeys(): Generator
    {
        yield 'space' => ['key' => self::MEMBER_ID . '/has space.jpg'];
        yield 'quote' => ['key' => self::MEMBER_ID . '/has"quote.jpg'];
        yield 'backslash' => ['key' => self::MEMBER_ID . '\\backslash.jpg'];
    }

    #[Test]
    #[DataProvider('illegalKeys')]
    #[TestDox('::of() rejects keys containing characters outside [A-Za-z0-9_-./] with InvalidProfilePhoto.')]
    public function rejects_illegal_characters(string $key): void
    {
        $this->expectException(InvalidProfilePhoto::class);

        ProfilePhoto::of($key, ImageFormat::Jpeg, new DateTimeImmutable());
    }

    #[Test]
    #[TestDox('::of() rejects a key containing a ".." path-traversal segment with InvalidProfilePhoto.')]
    public function rejects_path_traversal(): void
    {
        $this->expectException(InvalidProfilePhoto::class);

        ProfilePhoto::of('../../etc/passwd', ImageFormat::Jpeg, new DateTimeImmutable());
    }

    #[Test]
    #[TestDox('::version() returns the key basename without its extension.')]
    public function version_strips_directory_and_extension(): void
    {
        $photo = ProfilePhoto::of(
            self::MEMBER_ID . '/bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb.png',
            ImageFormat::Png,
            new DateTimeImmutable(),
        );

        self::assertSame('bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb', $photo->version());
    }

    #[Test]
    #[TestDox('equals() compares storageKey, format, and uploadedAt.')]
    public function equals_compares_all_fields(): void
    {
        $uploadedAt = new DateTimeImmutable('2026-05-24 12:00:00');
        $key = self::MEMBER_ID . '/aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa.jpg';

        $photo = ProfilePhoto::of($key, ImageFormat::Jpeg, $uploadedAt);
        $same = ProfilePhoto::of($key, ImageFormat::Jpeg, $uploadedAt);
        $differentFormat = ProfilePhoto::of($key, ImageFormat::Png, $uploadedAt);

        self::assertTrue($photo->equals($same));
        self::assertFalse($photo->equals($differentFormat));
    }
}
