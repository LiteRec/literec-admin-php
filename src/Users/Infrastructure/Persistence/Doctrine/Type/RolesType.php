<?php

declare(strict_types=1);

namespace App\Users\Infrastructure\Persistence\Doctrine\Type;

use App\Users\Domain\ValueObject\Role;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\JsonType;

/**
 * Stores a list<Role> as a JSON array of enum values (e.g. ["ROLE_ADMIN"])
 * and hydrates the column back into a list<Role>.
 */
final class RolesType extends JsonType
{
    public const NAME = 'users_roles';

    public function getName(): string
    {
        return self::NAME;
    }

    /**
     * @return list<Role>
     */
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): array
    {
        if ($value === null) {
            return [];
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

        return $roles;
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return parent::convertToDatabaseValue(null, $platform);
        }

        if (!is_array($value)) {
            throw new \UnexpectedValueException(sprintf(
                'Expected list<Role> or null, got %s.',
                get_debug_type($value),
            ));
        }

        $values = [];
        foreach ($value as $role) {
            if (!$role instanceof Role) {
                throw new \UnexpectedValueException(sprintf(
                    'Roles list entry expected to be Role, got %s.',
                    get_debug_type($role),
                ));
            }
            $values[] = $role->value;
        }

        return parent::convertToDatabaseValue($values, $platform);
    }
}
