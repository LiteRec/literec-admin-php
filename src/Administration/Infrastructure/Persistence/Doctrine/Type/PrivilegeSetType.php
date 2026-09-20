<?php

declare(strict_types=1);

namespace App\Administration\Infrastructure\Persistence\Doctrine\Type;

use App\Administration\Domain\Exception\UnknownPrivilege;
use App\Administration\Domain\Privilege;
use App\Administration\Domain\ValueObject\PrivilegeSet;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\JsonType;

/**
 * Stores a {@see PrivilegeSet} as a JSON array of {@see Privilege} backing
 * values (e.g. ["VIEW_USERS"]) and hydrates the column back into a
 * PrivilegeSet instance, exactly as
 * {@see \App\Users\Infrastructure\Persistence\Doctrine\Type\RolesType}
 * does for {@see \App\Users\Domain\ValueObject\Roles}.
 *
 * A stored name with no matching {@see Privilege} case is dropped —
 * never fatal — reporting through {@see UnknownPrivilege} (logged by the
 * privilege lookup adapter, not raised here) so a stale entry from a
 * retired enum case goes inert and the role keeps loading. This is the
 * fail-closed contract LRA-266 sets: an absent privilege denies rather
 * than escalates.
 */
final class PrivilegeSetType extends JsonType
{
    public const NAME = 'administration_privilege_set';

    public function getName(): string
    {
        return self::NAME;
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): PrivilegeSet
    {
        if ($value === null) {
            return PrivilegeSet::none();
        }

        $decoded = parent::convertToPHPValue($value, $platform);

        if (!is_array($decoded)) {
            throw new \UnexpectedValueException('PrivilegeSet column did not decode to an array.');
        }

        $privileges = [];
        foreach ($decoded as $privilegeName) {
            if (!is_string($privilegeName)) {
                throw new \UnexpectedValueException(sprintf(
                    'PrivilegeSet column entry expected to be a string, got %s.',
                    get_debug_type($privilegeName),
                ));
            }

            $privilege = Privilege::tryFrom($privilegeName);

            // A retired enum case is dropped rather than raising
            // UnknownPrivilege here: the type-conversion boundary is not
            // a privilege *check*, and failing the whole row load over
            // one stale grant would brick every role that once held it.
            if ($privilege !== null) {
                $privileges[] = $privilege;
            }
        }

        return PrivilegeSet::of(...$privileges);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return parent::convertToDatabaseValue(null, $platform);
        }

        if (!$value instanceof PrivilegeSet) {
            throw new \UnexpectedValueException(sprintf(
                'Expected PrivilegeSet or null, got %s.',
                get_debug_type($value),
            ));
        }

        return parent::convertToDatabaseValue($value->toNames(), $platform);
    }
}
