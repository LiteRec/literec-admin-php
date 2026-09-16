<?php

declare(strict_types=1);

namespace App\Users\Infrastructure\Persistence\Doctrine\Type;

use App\Users\Domain\ValueObject\Role;
use App\Users\Domain\ValueObject\Roles;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\JsonType;

/**
 * Stores a {@see Roles} collection as a JSON array of enum values
 * (e.g. ["ROLE_ADMIN"]) and hydrates the column back into a Roles instance.
 * The persisted shape is unchanged by LRA-236: still a JSON array of Role
 * enum string values, just wrapped for the PHP side.
 */
final class RolesType extends JsonType
{
    public const NAME = 'users_roles';

    public function getName(): string
    {
        return self::NAME;
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): Roles
    {
        if ($value === null) {
            return Roles::none();
        }

        $decoded = parent::convertToPHPValue($value, $platform);

        if (!is_array($decoded)) {
            throw new \UnexpectedValueException('Roles column did not decode to an array.');
        }

        $roles = [];
        foreach ($decoded as $roleValue) {
            if (!is_string($roleValue)) {
                throw new \UnexpectedValueException(sprintf(
                    'Roles column entry expected to be a string, got %s.',
                    get_debug_type($roleValue),
                ));
            }
            $roles[] = Role::from($roleValue);
        }

        return Roles::of(...$roles);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return parent::convertToDatabaseValue(null, $platform);
        }

        if (!$value instanceof Roles) {
            throw new \UnexpectedValueException(sprintf(
                'Expected Roles or null, got %s.',
                get_debug_type($value),
            ));
        }

        return parent::convertToDatabaseValue($value->toStrings(), $platform);
    }
}
