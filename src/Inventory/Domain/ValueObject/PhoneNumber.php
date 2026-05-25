<?php

declare(strict_types=1);

namespace App\Inventory\Domain\ValueObject;

use App\Inventory\Domain\Exception\InvalidPhoneNumber;
use Stringable;

/**
 * Phone number, stored in a loosely-normalized form:
 *   - whitespace, parentheses, and hyphens are stripped
 *   - a single leading "+" is preserved if present
 *   - all remaining characters must be digits
 *
 * Per-region validation (E.164, NANP etc.) is deliberately out of scope here.
 *
 * Per-context copy of the Households VO: cross-context VO sharing is
 * forbidden, so the validation contract is duplicated here.
 */
final readonly class PhoneNumber implements Stringable
{
    public string $value;

    private function __construct(string $value)
    {
        $this->value = $value;
    }

    public static function of(string $value): self
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            throw InvalidPhoneNumber::empty();
        }

        // Strip whitespace, parens, and hyphens; preserve leading "+".
        $stripped = preg_replace('/[\s()\-]/', '', $trimmed);
        if (! is_string($stripped) || $stripped === '') {
            throw InvalidPhoneNumber::empty();
        }

        $hasPlus = str_starts_with($stripped, '+');
        $digits = $hasPlus ? substr($stripped, 1) : $stripped;

        if ($digits === '' || preg_match('/^[0-9]+$/', $digits) !== 1) {
            throw InvalidPhoneNumber::illegalCharacters();
        }

        $normalized = $hasPlus ? '+' . $digits : $digits;

        $normalizedLength = mb_strlen($normalized, 'UTF-8');

        if ($normalizedLength > InvalidPhoneNumber::MAX_LENGTH) {
            throw InvalidPhoneNumber::tooLong($normalizedLength);
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
