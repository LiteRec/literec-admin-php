<?php

declare(strict_types=1);

namespace App\Households\Infrastructure\Persistence\Doctrine\Type;

use App\Households\Domain\ValueObject\EmailAddress;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

final class EmailAddressType extends Type
{
    public const NAME = 'households_email_address';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL(['length' => 254]);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?EmailAddress
    {
        if ($value === null || $value instanceof EmailAddress) {
            return $value;
        }

        if (!is_string($value)) {
            throw new \UnexpectedValueException(sprintf(
                'Expected string or EmailAddress, got %s.',
                get_debug_type($value),
            ));
        }

        return EmailAddress::of($value);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof EmailAddress) {
            return $value->value;
        }

        throw new \UnexpectedValueException(sprintf(
            'Expected null or EmailAddress, got %s.',
            get_debug_type($value),
        ));
    }
}
