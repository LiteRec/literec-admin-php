<?php

declare(strict_types=1);

namespace App\Inventory\Domain\ValueObject;

use App\Inventory\Domain\Exception\InvalidComment;

/**
 * Free-form operator note attached to a stock receipt or movement.
 *
 * Trimmed on construction. Empty input is rejected — pass null at the
 * call site when no comment is provided. Capped at MAX_LENGTH characters
 * to keep the value safely projectable into a movement-history view.
 */
final readonly class Comment
{
    public const int MAX_LENGTH = 1000;

    public string $value;

    private function __construct(string $value)
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            throw InvalidComment::empty();
        }

        $length = mb_strlen($trimmed, 'UTF-8');

        if ($length > self::MAX_LENGTH) {
            throw InvalidComment::tooLong($length);
        }

        $this->value = $trimmed;
    }

    public static function of(string $value): self
    {
        return new self($value);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
