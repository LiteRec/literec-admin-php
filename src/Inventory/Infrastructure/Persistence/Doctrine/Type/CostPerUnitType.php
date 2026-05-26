<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Persistence\Doctrine\Type;

use App\Inventory\Domain\ValueObject\CostPerUnit;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use UnexpectedValueException;

final class CostPerUnitType extends Type
{
    public const NAME = 'inventory_cost_per_unit';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getBigIntTypeDeclarationSQL([]);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?CostPerUnit
    {
        if ($value === null || $value instanceof CostPerUnit) {
            return $value;
        }

        if (! is_int($value) && ! (is_string($value) && ctype_digit($value))) {
            throw new UnexpectedValueException(sprintf(
                'Expected integer, got %s.',
                get_debug_type($value),
            ));
        }

        return CostPerUnit::ofCents((int) $value);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?int
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof CostPerUnit) {
            return $value->cents;
        }

        throw new UnexpectedValueException(sprintf(
            'Expected null or CostPerUnit, got %s.',
            get_debug_type($value),
        ));
    }
}
