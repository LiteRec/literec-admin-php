<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Persistence\Doctrine\Type;

use App\Catalog\Domain\ListingKind;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use UnexpectedValueException;

final class ListingKindType extends Type
{
    public const NAME = 'catalog_listing_kind';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL(['length' => 16]);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?ListingKind
    {
        if ($value === null || $value instanceof ListingKind) {
            return $value;
        }

        if (! is_string($value)) {
            throw new UnexpectedValueException(sprintf(
                'Expected string or ListingKind, got %s.',
                get_debug_type($value),
            ));
        }

        return ListingKind::from($value);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof ListingKind) {
            return $value->value;
        }

        throw new UnexpectedValueException(sprintf(
            'Expected null or ListingKind, got %s.',
            get_debug_type($value),
        ));
    }
}
