<?php

declare(strict_types=1);

namespace App\Households\Domain\ValueObject;

use App\Households\Domain\Exception\UnsupportedImageFormat;

/**
 * The closed set of image MIME types a member profile photo may be stored
 * as (LRA-207). Backed by the MIME type string so it round-trips directly
 * through the `photo_format` column and the upload's detected MIME type
 * without an intermediate lookup table.
 */
enum ImageFormat: string
{
    case Jpeg = 'image/jpeg';
    case Png = 'image/png';
    case Webp = 'image/webp';

    public function extension(): string
    {
        return match ($this) {
            self::Jpeg => 'jpg',
            self::Png => 'png',
            self::Webp => 'webp',
        };
    }

    /**
     * @throws UnsupportedImageFormat when $mime is not one of the allowed
     *                                image formats.
     */
    public static function fromMimeType(string $mime): self
    {
        return self::tryFrom($mime) ?? throw UnsupportedImageFormat::for($mime);
    }
}
