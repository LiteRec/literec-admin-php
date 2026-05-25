<?php

declare(strict_types=1);

namespace App\Catalog\Domain\ValueObject;

use App\Catalog\Domain\Exception\InvalidListingCode;
use Stringable;

/**
 * External-facing item code (POS barcode, SKU, etc.).
 *
 * Whitespace is trimmed and the value is uppercased on construction so
 * "abc-1" and " ABC-1 " produce the same canonical code and the unique
 * constraint at the repository level catches duplicates regardless of
 * input casing.
 */
final readonly class ListingCode implements Stringable
{
    private const ALLOWED_PATTERN = '/^[A-Z0-9_-]+$/';

    public string $value;

    private function __construct(string $value)
    {
        $this->value = $value;
    }

    public static function of(string $value): self
    {
        $normalized = strtoupper(trim($value));

        if ($normalized === '') {
            throw InvalidListingCode::empty();
        }

        $length = strlen($normalized);

        if ($length > InvalidListingCode::MAX_LENGTH) {
            throw InvalidListingCode::tooLong($length);
        }

        if (preg_match(self::ALLOWED_PATTERN, $normalized) !== 1) {
            throw InvalidListingCode::illegalCharacters($normalized);
        }

        return new self($normalized);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
