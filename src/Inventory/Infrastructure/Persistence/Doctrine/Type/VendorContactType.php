<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Persistence\Doctrine\Type;

use App\Inventory\Domain\Exception\InvalidVendorContact;
use App\Inventory\Domain\ValueObject\VendorContact;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use UnexpectedValueException;

final class VendorContactType extends Type
{
    public const NAME = 'inventory_vendor_contact';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL(['length' => InvalidVendorContact::MAX_LENGTH]);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?VendorContact
    {
        if ($value === null || $value instanceof VendorContact) {
            return $value;
        }

        if (! is_string($value)) {
            throw new UnexpectedValueException(sprintf(
                'Expected string or VendorContact, got %s.',
                get_debug_type($value),
            ));
        }

        return VendorContact::of($value);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof VendorContact) {
            return $value->value;
        }

        throw new UnexpectedValueException(sprintf(
            'Expected null or VendorContact, got %s.',
            get_debug_type($value),
        ));
    }
}
