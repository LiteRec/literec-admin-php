<?php

declare(strict_types=1);

namespace App\Administration\Infrastructure\Persistence\Doctrine\Type;

use App\Administration\Domain\ValueObject\RoleName;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use UnexpectedValueException;

final class RoleNameType extends Type
{
    public const NAME = 'administration_role_name';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL(['length' => RoleName::MAX_LENGTH]);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?RoleName
    {
        if ($value === null || $value instanceof RoleName) {
            return $value;
        }

        if (!is_string($value)) {
            throw new UnexpectedValueException(sprintf(
                'Expected string or RoleName, got %s.',
                get_debug_type($value),
            ));
        }

        return RoleName::of($value);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof RoleName) {
            return $value->value;
        }

        throw new UnexpectedValueException(sprintf(
            'Expected null or RoleName, got %s.',
            get_debug_type($value),
        ));
    }
}
