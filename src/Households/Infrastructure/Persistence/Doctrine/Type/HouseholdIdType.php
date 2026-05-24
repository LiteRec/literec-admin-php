<?php

declare(strict_types=1);

namespace App\Households\Infrastructure\Persistence\Doctrine\Type;

use App\Households\Domain\ValueObject\HouseholdId;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

final class HouseholdIdType extends Type
{
    public const NAME = 'households_household_id';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL(['length' => 36, 'fixed' => true]);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?HouseholdId
    {
        if ($value === null || $value instanceof HouseholdId) {
            return $value;
        }

        if (!is_string($value)) {
            throw new \UnexpectedValueException(sprintf(
                'Expected string or HouseholdId, got %s.',
                get_debug_type($value),
            ));
        }

        return HouseholdId::fromString($value);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof HouseholdId) {
            return $value->value;
        }

        throw new \UnexpectedValueException(sprintf(
            'Expected null or HouseholdId, got %s.',
            get_debug_type($value),
        ));
    }
}
