<?php

declare(strict_types=1);

namespace App\Households\Infrastructure\Persistence\Doctrine\Type;

use App\Shared\Domain\ValueObject\PhoneNumber;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

final class PhoneNumberType extends Type
{
    public const NAME = 'households_phone_number';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL(['length' => 32]);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?PhoneNumber
    {
        if ($value === null || $value instanceof PhoneNumber) {
            return $value;
        }

        if (!is_string($value)) {
            throw new \UnexpectedValueException(sprintf(
                'Expected string or PhoneNumber, got %s.',
                get_debug_type($value),
            ));
        }

        return PhoneNumber::of($value);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof PhoneNumber) {
            return $value->value;
        }

        throw new \UnexpectedValueException(sprintf(
            'Expected null or PhoneNumber, got %s.',
            get_debug_type($value),
        ));
    }
}
