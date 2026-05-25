<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Persistence\Doctrine\Type;

use App\Catalog\Domain\Exception\InvalidListingCode;
use App\Catalog\Domain\ValueObject\ListingCode;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use UnexpectedValueException;

final class ListingCodeType extends Type
{
    public const NAME = 'catalog_listing_code';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL(['length' => InvalidListingCode::MAX_LENGTH]);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?ListingCode
    {
        if ($value === null || $value instanceof ListingCode) {
            return $value;
        }

        if (! is_string($value)) {
            throw new UnexpectedValueException(sprintf(
                'Expected string or ListingCode, got %s.',
                get_debug_type($value),
            ));
        }

        return ListingCode::of($value);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof ListingCode) {
            return $value->value;
        }

        throw new UnexpectedValueException(sprintf(
            'Expected null or ListingCode, got %s.',
            get_debug_type($value),
        ));
    }
}
