<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Persistence\Doctrine\Type;

use App\Inventory\Domain\ValueObject\FacilityCode;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use UnexpectedValueException;

final class FacilityCodeType extends Type
{
    public const NAME = 'inventory_facility_code';
    private const MAX_LENGTH = 32;

    public function getName(): string
    {
        return self::NAME;
    }

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL(['length' => self::MAX_LENGTH]);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?FacilityCode
    {
        if ($value === null || $value instanceof FacilityCode) {
            return $value;
        }

        if (! is_string($value)) {
            throw new UnexpectedValueException(sprintf(
                'Expected string or FacilityCode, got %s.',
                get_debug_type($value),
            ));
        }

        return FacilityCode::fromString($value);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof FacilityCode) {
            return $value->value;
        }

        throw new UnexpectedValueException(sprintf(
            'Expected null or FacilityCode, got %s.',
            get_debug_type($value),
        ));
    }
}
