<?php

declare(strict_types=1);

namespace App\Administration\Infrastructure\Persistence\Doctrine\Type;

use App\Administration\Domain\ValueObject\RoleDescription;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use UnexpectedValueException;

/**
 * Maps to a TEXT column (matching the legacy `security_roles.description`
 * width, but NOT NULL DEFAULT '' rather than legacy's NOT NULL with a
 * null default — see {@see RoleDescription}).
 */
final class RoleDescriptionType extends Type
{
    public const NAME = 'administration_role_description';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getClobTypeDeclarationSQL($column);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?RoleDescription
    {
        if ($value === null || $value instanceof RoleDescription) {
            return $value;
        }

        if (!is_string($value)) {
            throw new UnexpectedValueException(sprintf(
                'Expected string or RoleDescription, got %s.',
                get_debug_type($value),
            ));
        }

        return RoleDescription::of($value);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof RoleDescription) {
            return $value->value;
        }

        throw new UnexpectedValueException(sprintf(
            'Expected null or RoleDescription, got %s.',
            get_debug_type($value),
        ));
    }
}
