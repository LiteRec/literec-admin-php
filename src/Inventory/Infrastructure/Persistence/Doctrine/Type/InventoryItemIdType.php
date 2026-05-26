<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Persistence\Doctrine\Type;

use App\Inventory\Domain\ValueObject\InventoryItemId;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use UnexpectedValueException;

final class InventoryItemIdType extends Type
{
    public const NAME = 'inventory_item_id';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL(['length' => 36, 'fixed' => true]);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?InventoryItemId
    {
        if ($value === null || $value instanceof InventoryItemId) {
            return $value;
        }

        if (! is_string($value)) {
            throw new UnexpectedValueException(sprintf(
                'Expected string or InventoryItemId, got %s.',
                get_debug_type($value),
            ));
        }

        return InventoryItemId::fromString($value);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof InventoryItemId) {
            return $value->value;
        }

        throw new UnexpectedValueException(sprintf(
            'Expected null or InventoryItemId, got %s.',
            get_debug_type($value),
        ));
    }
}
