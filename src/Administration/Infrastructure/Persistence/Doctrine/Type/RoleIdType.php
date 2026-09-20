<?php

declare(strict_types=1);

namespace App\Administration\Infrastructure\Persistence\Doctrine\Type;

use App\Administration\Domain\ValueObject\RoleId;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use UnexpectedValueException;

final class RoleIdType extends Type
{
    public const NAME = 'administration_role_id';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL(['length' => 36, 'fixed' => true]);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?RoleId
    {
        if ($value === null || $value instanceof RoleId) {
            return $value;
        }

        if (!is_string($value)) {
            throw new UnexpectedValueException(sprintf(
                'Expected string or RoleId, got %s.',
                get_debug_type($value),
            ));
        }

        return RoleId::fromString($value);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof RoleId) {
            return $value->value;
        }

        throw new UnexpectedValueException(sprintf(
            'Expected null or RoleId, got %s.',
            get_debug_type($value),
        ));
    }
}
