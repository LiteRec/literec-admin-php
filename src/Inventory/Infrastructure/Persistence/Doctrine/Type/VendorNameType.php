<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Persistence\Doctrine\Type;

use App\Inventory\Domain\Exception\InvalidVendorName;
use App\Inventory\Domain\ValueObject\VendorName;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use UnexpectedValueException;

final class VendorNameType extends Type
{
    public const NAME = 'inventory_vendor_name';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL(['length' => InvalidVendorName::MAX_LENGTH]);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?VendorName
    {
        if ($value === null || $value instanceof VendorName) {
            return $value;
        }

        if (! is_string($value)) {
            throw new UnexpectedValueException(sprintf(
                'Expected string or VendorName, got %s.',
                get_debug_type($value),
            ));
        }

        return VendorName::of($value);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof VendorName) {
            return $value->value;
        }

        throw new UnexpectedValueException(sprintf(
            'Expected null or VendorName, got %s.',
            get_debug_type($value),
        ));
    }
}
