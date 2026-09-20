<?php

declare(strict_types=1);

namespace App\Administration\Domain\ValueObject;

use App\Administration\Domain\Exception\InvalidRevocationReason;

/**
 * Free-text reason recorded on an {@see \App\Administration\Domain\AdministratorTenure}
 * when it is closed. Unlike {@see RoleDescription}, empty is not a valid
 * state — a revocation is a consequential act and must say why, matching
 * the free-text reason fields already required elsewhere in this
 * codebase (e.g. {@see \App\Households\Domain\ValueObject\Deactivation}).
 */
final readonly class RevocationReason
{
    public const int MAX_LENGTH = 255;

    public string $value;

    private function __construct(string $value)
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            throw InvalidRevocationReason::empty();
        }

        $length = mb_strlen($trimmed, 'UTF-8');

        if ($length > self::MAX_LENGTH) {
            throw InvalidRevocationReason::tooLong($length);
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
