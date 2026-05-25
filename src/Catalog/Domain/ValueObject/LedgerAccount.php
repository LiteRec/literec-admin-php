<?php

declare(strict_types=1);

namespace App\Catalog\Domain\ValueObject;

use App\Catalog\Domain\Exception\InvalidLedgerAccount;
use Stringable;

/**
 * Reference to a general-ledger account that revenue from this Listing
 * posts into. Catalog does not own the GL chart — it only owns the
 * typed reference so downstream reporting/transactions can resolve it.
 */
final readonly class LedgerAccount implements Stringable
{
    private const ALLOWED_PATTERN = '/^[A-Z0-9-]+$/';

    public string $value;

    private function __construct(string $value)
    {
        $this->value = $value;
    }

    public static function of(string $value): self
    {
        $normalized = strtoupper(trim($value));
        $length = strlen($normalized);

        if ($length < InvalidLedgerAccount::MIN_LENGTH || $length > InvalidLedgerAccount::MAX_LENGTH) {
            throw InvalidLedgerAccount::badLength($length);
        }

        if (preg_match(self::ALLOWED_PATTERN, $normalized) !== 1) {
            throw InvalidLedgerAccount::illegalCharacters($normalized);
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
