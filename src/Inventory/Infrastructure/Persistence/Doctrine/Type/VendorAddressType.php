<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Persistence\Doctrine\Type;

use App\Inventory\Domain\ValueObject\VendorAddress;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\JsonType;
use UnexpectedValueException;

/**
 * Serialises an optional {@see VendorAddress} as a JSONB object so the
 * aggregate can hold the address as a nested value object without
 * forcing each address column to be nullable on the vendor table.
 *
 * A Doctrine embeddable was rejected here: ORM 3 has no "null embeddable
 * vs. empty embeddable" distinction, so an optional embeddable would
 * require nullable columns and break the VO's own invariant that all
 * fields are non-null when the address exists. A single JSONB column is
 * either NULL (no address) or a complete object that the VO factory can
 * re-validate.
 */
final class VendorAddressType extends JsonType
{
    public const NAME = 'inventory_vendor_address';

    public function getName(): string
    {
        return self::NAME;
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?VendorAddress
    {
        if ($value === null || $value instanceof VendorAddress) {
            return $value;
        }

        $decoded = parent::convertToPHPValue($value, $platform);

        if (! is_array($decoded)) {
            throw new UnexpectedValueException('Vendor address column did not decode to an object.');
        }

        foreach (['street', 'unit', 'city', 'state', 'postalCode', 'country'] as $key) {
            if (! array_key_exists($key, $decoded)) {
                throw new UnexpectedValueException(sprintf(
                    'Vendor address payload missing required key "%s".',
                    $key,
                ));
            }
        }

        $street = $decoded['street'];
        $unit = $decoded['unit'];
        $city = $decoded['city'];
        $state = $decoded['state'];
        $postalCode = $decoded['postalCode'];
        $country = $decoded['country'];

        if (
            ! is_string($street)
            || ($unit !== null && ! is_string($unit))
            || ! is_string($city)
            || ! is_string($state)
            || ! is_string($postalCode)
            || ! is_string($country)
        ) {
            throw new UnexpectedValueException('Vendor address payload has unexpected member types.');
        }

        return VendorAddress::of($street, $unit, $city, $state, $postalCode, $country);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return parent::convertToDatabaseValue(null, $platform);
        }

        if (! $value instanceof VendorAddress) {
            throw new UnexpectedValueException(sprintf(
                'Expected null or VendorAddress, got %s.',
                get_debug_type($value),
            ));
        }

        return parent::convertToDatabaseValue([
            'street'     => $value->street,
            'unit'       => $value->unit,
            'city'       => $value->city,
            'state'      => $value->state,
            'postalCode' => $value->postalCode,
            'country'    => $value->country,
        ], $platform);
    }
}
