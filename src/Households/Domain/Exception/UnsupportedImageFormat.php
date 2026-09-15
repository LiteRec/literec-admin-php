<?php

declare(strict_types=1);

namespace App\Households\Domain\Exception;

use DomainException;

final class UnsupportedImageFormat extends DomainException implements HouseholdsDomainException
{
    /**
     * @param string $mime Not embedded in the message — mirrors the
     *                     codebase-wide convention of keeping
     *                     caller-controlled values out of exception text.
     */
    public static function for(string $mime): self
    {
        unset($mime);

        return new self('Photo must be a JPEG, PNG, or WEBP image.');
    }
}
