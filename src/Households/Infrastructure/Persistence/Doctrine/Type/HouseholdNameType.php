<?php

declare(strict_types=1);

namespace App\Households\Infrastructure\Persistence\Doctrine\Type;

use App\Households\Domain\ValueObject\HouseholdName;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

final class HouseholdNameType extends Type
{
    public const NAME = 'households_household_name';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL(['length' => 200]);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?HouseholdName
    {
        if ($value === null || $value instanceof HouseholdName) {
            return $value;
        }

        if (!is_string($value)) {
            throw new \UnexpectedValueException(sprintf(
                'Expected string or HouseholdName, got %s.',
                get_debug_type($value),
            ));
        }

        return HouseholdName::of($value);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof HouseholdName) {
            return $value->value;
        }

        throw new \UnexpectedValueException(sprintf(
            'Expected null or HouseholdName, got %s.',
            get_debug_type($value),
        ));
    }
}
