<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Persistence\Doctrine\Type;

use App\Inventory\Domain\ValueObject\ItemGroupName;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use UnexpectedValueException;

final class ItemGroupNameType extends Type
{
    public const NAME = 'inventory_item_group_name';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL(['length' => ItemGroupName::MAX_LENGTH]);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?ItemGroupName
    {
        if ($value === null || $value instanceof ItemGroupName) {
            return $value;
        }

        if (! is_string($value)) {
            throw new UnexpectedValueException(sprintf(
                'Expected string or ItemGroupName, got %s.',
                get_debug_type($value),
            ));
        }

        return ItemGroupName::of($value);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof ItemGroupName) {
            return $value->value;
        }

        throw new UnexpectedValueException(sprintf(
            'Expected null or ItemGroupName, got %s.',
            get_debug_type($value),
        ));
    }
}
