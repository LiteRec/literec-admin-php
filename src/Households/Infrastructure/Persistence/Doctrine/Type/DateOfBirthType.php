<?php

declare(strict_types=1);

namespace App\Households\Infrastructure\Persistence\Doctrine\Type;

use App\Households\Domain\ValueObject\DateOfBirth;
use DateTimeImmutable;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

/**
 * Stores a {@see DateOfBirth} as a DATE column.
 *
 * On hydration uses {@see DateOfBirth::fromString()} (the no-clock factory)
 * because the value was already validated at write time — re-checking the
 * not-future invariant would couple persistence reads to a clock and risk
 * false negatives if data is migrated from another timezone.
 */
final class DateOfBirthType extends Type
{
    public const NAME = 'households_date_of_birth';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getDateTypeDeclarationSQL($column);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?DateOfBirth
    {
        if ($value === null || $value instanceof DateOfBirth) {
            return $value;
        }

        if ($value instanceof DateTimeImmutable) {
            return DateOfBirth::fromString($value->format('Y-m-d'));
        }

        if (!is_string($value)) {
            throw new \UnexpectedValueException(sprintf(
                'Expected string, DateTimeImmutable, or DateOfBirth, got %s.',
                get_debug_type($value),
            ));
        }

        return DateOfBirth::fromString($value);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof DateOfBirth) {
            return $value->value->format('Y-m-d');
        }

        throw new \UnexpectedValueException(sprintf(
            'Expected null or DateOfBirth, got %s.',
            get_debug_type($value),
        ));
    }
}
