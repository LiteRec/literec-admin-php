<?php

declare(strict_types=1);

namespace App\Users\Infrastructure\Persistence\Doctrine\Type;

use App\Users\Domain\ValueObject\HashedPassword;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

final class HashedPasswordType extends Type
{
    public const NAME = 'users_hashed_password';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL($column);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?HashedPassword
    {
        if ($value === null || $value instanceof HashedPassword) {
            return $value;
        }

        if (!is_string($value)) {
            throw new \UnexpectedValueException(sprintf(
                'Expected string or HashedPassword, got %s.',
                get_debug_type($value),
            ));
        }

        return HashedPassword::fromHash($value);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof HashedPassword) {
            return $value->value;
        }

        throw new \UnexpectedValueException(sprintf(
            'Expected null or HashedPassword, got %s.',
            get_debug_type($value),
        ));
    }
}
