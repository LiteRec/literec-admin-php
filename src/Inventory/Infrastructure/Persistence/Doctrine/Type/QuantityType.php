<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Persistence\Doctrine\Type;

use App\Inventory\Domain\ValueObject\Quantity;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use UnexpectedValueException;

final class QuantityType extends Type
{
    public const NAME = 'inventory_quantity';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getIntegerTypeDeclarationSQL([]);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?Quantity
    {
        if ($value === null || $value instanceof Quantity) {
            return $value;
        }

        if (! is_int($value) && ! (is_string($value) && ctype_digit($value))) {
            throw new UnexpectedValueException(sprintf(
                'Expected integer, got %s.',
                get_debug_type($value),
            ));
        }

        return Quantity::ofUnits((int) $value);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?int
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof Quantity) {
            return $value->units;
        }

        throw new UnexpectedValueException(sprintf(
            'Expected null or Quantity, got %s.',
            get_debug_type($value),
        ));
    }
}
