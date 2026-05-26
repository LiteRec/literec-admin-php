<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Persistence\Doctrine\Type;

use App\Inventory\Domain\ValueObject\ReorderThreshold;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use UnexpectedValueException;

/**
 * Persists {@see ReorderThreshold} as a nullable integer column.
 *
 * NULL in the column maps to {@see ReorderThreshold::none()} — both
 * states mean "low-stock alerts disabled for this item-facility pair."
 * Stored as a single column rather than a nullable embeddable so the
 * "sentinel none" semantics survive the hydration boundary.
 */
final class ReorderThresholdType extends Type
{
    public const NAME = 'inventory_reorder_threshold';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getIntegerTypeDeclarationSQL(['notnull' => false]);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ReorderThreshold
    {
        if ($value === null) {
            return ReorderThreshold::none();
        }

        if ($value instanceof ReorderThreshold) {
            return $value;
        }

        if (! is_int($value) && ! (is_string($value) && ctype_digit($value))) {
            throw new UnexpectedValueException(sprintf(
                'Expected integer or null, got %s.',
                get_debug_type($value),
            ));
        }

        return ReorderThreshold::ofUnits((int) $value);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?int
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof ReorderThreshold) {
            return $value->units;
        }

        throw new UnexpectedValueException(sprintf(
            'Expected null or ReorderThreshold, got %s.',
            get_debug_type($value),
        ));
    }
}
