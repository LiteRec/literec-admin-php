<?php

declare(strict_types=1);

namespace App\Households\Infrastructure\Persistence\Doctrine\Type;

use App\Households\Domain\ValueObject\Weight;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

final class WeightType extends Type
{
    public const NAME = 'households_weight';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getSmallIntTypeDeclarationSQL($column);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?Weight
    {
        if ($value === null || $value instanceof Weight) {
            return $value;
        }

        if (!is_int($value) && !(is_string($value) && is_numeric($value))) {
            throw new \UnexpectedValueException(sprintf(
                'Expected int, numeric string, or Weight, got %s.',
                get_debug_type($value),
            ));
        }

        return Weight::ofPounds((int) $value);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?int
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof Weight) {
            return $value->pounds;
        }

        throw new \UnexpectedValueException(sprintf(
            'Expected null or Weight, got %s.',
            get_debug_type($value),
        ));
    }
}
