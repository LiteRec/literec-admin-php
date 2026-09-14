<?php

declare(strict_types=1);

namespace App\Households\Infrastructure\Persistence\Doctrine\Type;

use App\Households\Domain\ValueObject\Height;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

final class HeightType extends Type
{
    public const NAME = 'households_height';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getSmallIntTypeDeclarationSQL($column);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?Height
    {
        if ($value === null || $value instanceof Height) {
            return $value;
        }

        if (!is_int($value) && !(is_string($value) && is_numeric($value))) {
            throw new \UnexpectedValueException(sprintf(
                'Expected int, numeric string, or Height, got %s.',
                get_debug_type($value),
            ));
        }

        return Height::ofInches((int) $value);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?int
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof Height) {
            return $value->inches;
        }

        throw new \UnexpectedValueException(sprintf(
            'Expected null or Height, got %s.',
            get_debug_type($value),
        ));
    }
}
