<?php

declare(strict_types=1);

namespace App\Administration\Infrastructure\Persistence\Doctrine\Type;

use App\Administration\Domain\ValueObject\SeniorityLevel;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use UnexpectedValueException;

/**
 * Persists {@see SeniorityLevel} as a SMALLINT column, matching the
 * migration's `seniority SMALLINT NOT NULL`.
 */
final class SeniorityLevelType extends Type
{
    public const NAME = 'administration_seniority_level';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getSmallIntTypeDeclarationSQL($column);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?SeniorityLevel
    {
        if ($value === null || $value instanceof SeniorityLevel) {
            return $value;
        }

        if (!is_int($value) && !(is_string($value) && ctype_digit($value))) {
            throw new UnexpectedValueException(sprintf(
                'Expected integer or SeniorityLevel, got %s.',
                get_debug_type($value),
            ));
        }

        return SeniorityLevel::of((int) $value);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?int
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof SeniorityLevel) {
            return $value->value;
        }

        throw new UnexpectedValueException(sprintf(
            'Expected null or SeniorityLevel, got %s.',
            get_debug_type($value),
        ));
    }
}
