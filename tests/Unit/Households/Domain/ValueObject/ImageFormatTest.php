<?php

declare(strict_types=1);

namespace App\Tests\Unit\Households\Domain\ValueObject;

use App\Households\Domain\Exception\UnsupportedImageFormat;
use App\Households\Domain\ValueObject\ImageFormat;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[Small]
final class ImageFormatTest extends TestCase
{
    /**
     * @return Generator<string, array{mime: string, expected: ImageFormat, extension: string}>
     */
    public static function supportedFormats(): Generator
    {
        yield 'jpeg' => ['mime' => 'image/jpeg', 'expected' => ImageFormat::Jpeg, 'extension' => 'jpg'];
        yield 'png' => ['mime' => 'image/png', 'expected' => ImageFormat::Png, 'extension' => 'png'];
        yield 'webp' => ['mime' => 'image/webp', 'expected' => ImageFormat::Webp, 'extension' => 'webp'];
    }

    #[Test]
    #[DataProvider('supportedFormats')]
    #[TestDox('::fromMimeType() maps a supported MIME type to the matching case and extension().')]
    public function from_mime_type_maps_supported_formats(string $mime, ImageFormat $expected, string $extension): void
    {
        $format = ImageFormat::fromMimeType($mime);

        self::assertSame($expected, $format);
        self::assertSame($extension, $format->extension());
    }

    #[Test]
    #[TestDox('::fromMimeType() throws UnsupportedImageFormat for an unrecognized MIME type.')]
    public function from_mime_type_rejects_unknown_mime_type(): void
    {
        $this->expectException(UnsupportedImageFormat::class);

        ImageFormat::fromMimeType('text/plain');
    }
}
