<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Persistence\Doctrine\Type;

use App\Catalog\Domain\ValueObject\ListingId;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use UnexpectedValueException;

/**
 * Inventory-context Doctrine type for Catalog's ListingId published-language VO.
 *
 * Catalog ships its own ListingIdType for use in catalog_listings; this
 * per-context copy keeps the Inventory mapping self-contained so the
 * Inventory schema does not implicitly depend on Catalog's mapping
 * registration order.
 */
final class ListingIdType extends Type
{
    public const NAME = 'inventory_listing_id';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL(['length' => 36, 'fixed' => true]);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?ListingId
    {
        if ($value === null || $value instanceof ListingId) {
            return $value;
        }

        if (! is_string($value)) {
            throw new UnexpectedValueException(sprintf(
                'Expected string or ListingId, got %s.',
                get_debug_type($value),
            ));
        }

        return ListingId::fromString($value);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof ListingId) {
            return $value->value;
        }

        throw new UnexpectedValueException(sprintf(
            'Expected null or ListingId, got %s.',
            get_debug_type($value),
        ));
    }
}
