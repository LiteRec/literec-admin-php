<?php

declare(strict_types=1);

namespace App\Households\Domain\ValueObject;

use App\Households\Domain\Exception\InvalidEmailAddress;
use Stringable;

final readonly class EmailAddress implements Stringable
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
            throw InvalidEmailAddress::empty();
        }

        $length = mb_strlen($trimmed, 'UTF-8');

        if ($length > InvalidEmailAddress::MAX_LENGTH) {
            throw InvalidEmailAddress::tooLong($length);
        }

        $lowered = mb_strtolower($trimmed, 'UTF-8');

        if (filter_var($lowered, FILTER_VALIDATE_EMAIL) === false) {
            throw InvalidEmailAddress::malformed();
        }

        return new self($lowered);
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
