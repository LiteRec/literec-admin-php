<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Persistence\Doctrine\Type;

use App\Inventory\Domain\ValueObject\ItemLinkId;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use UnexpectedValueException;

final class ItemLinkIdType extends Type
{
    public const NAME = 'inventory_item_link_id';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL(['length' => 36, 'fixed' => true]);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?ItemLinkId
    {
        if ($value === null || $value instanceof ItemLinkId) {
            return $value;
        }

        if (! is_string($value)) {
            throw new UnexpectedValueException(sprintf(
                'Expected string or ItemLinkId, got %s.',
                get_debug_type($value),
            ));
        }

        return ItemLinkId::fromString($value);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof ItemLinkId) {
            return $value->value;
        }

        throw new UnexpectedValueException(sprintf(
            'Expected null or ItemLinkId, got %s.',
            get_debug_type($value),
        ));
    }
}
