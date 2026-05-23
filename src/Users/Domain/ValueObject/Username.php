<?php

declare(strict_types=1);

namespace App\Users\Domain\ValueObject;

use App\Users\Domain\Exception\InvalidUsername;
use Stringable;

final readonly class Username implements Stringable
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
            throw InvalidUsername::empty();
        }

        $length = mb_strlen($trimmed, 'UTF-8');

        if ($length > InvalidUsername::MAX_LENGTH) {
            throw InvalidUsername::tooLong($length);
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
