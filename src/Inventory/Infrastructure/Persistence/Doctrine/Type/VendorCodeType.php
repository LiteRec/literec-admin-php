<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Persistence\Doctrine\Type;

use App\Inventory\Domain\ValueObject\VendorCode;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use UnexpectedValueException;

final class VendorCodeType extends Type
{
    public const NAME = 'inventory_vendor_code';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL(['length' => VendorCode::MAX_LENGTH]);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?VendorCode
    {
        if ($value === null || $value instanceof VendorCode) {
            return $value;
        }

        if (! is_string($value)) {
            throw new UnexpectedValueException(sprintf(
                'Expected string or VendorCode, got %s.',
                get_debug_type($value),
            ));
        }

        return VendorCode::fromString($value);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof VendorCode) {
            return $value->value;
        }

        throw new UnexpectedValueException(sprintf(
            'Expected null or VendorCode, got %s.',
            get_debug_type($value),
        ));
    }
}
