<?php

declare(strict_types=1);

namespace App\Households\Domain\ValueObject;

use App\Households\Domain\Exception\InvalidTransactionReference;
use Stringable;

/**
 * Opaque reference to a single transaction, as selected by staff on the
 * Transaction History card for a split (LRA-209).
 *
 * The Transactions bounded context does not exist yet and its id format
 * is not decided, so this value object makes no shape assumption (not a
 * UUID, not numeric) — it only rejects empty/whitespace-only strings.
 */
final readonly class TransactionReference implements Stringable
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
            throw InvalidTransactionReference::empty();
        }

        return new self($trimmed);
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
