<?php

declare(strict_types=1);

namespace App\Households\Infrastructure\Persistence\Doctrine\Type;

use App\Households\Domain\ValueObject\MemberId;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

final class MemberIdType extends Type
{
    public const NAME = 'households_member_id';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL(['length' => 36, 'fixed' => true]);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?MemberId
    {
        if ($value === null || $value instanceof MemberId) {
            return $value;
        }

        if (!is_string($value)) {
            throw new \UnexpectedValueException(sprintf(
                'Expected string or MemberId, got %s.',
                get_debug_type($value),
            ));
        }

        return MemberId::fromString($value);
    }

    /**
     * Accepts an already-converted string in addition to a {@see MemberId}
     * instance (LRA-210): Doctrine's derived-identity handling for
     * {@see \App\Households\Domain\HouseholdAffiliation}'s
     * association-key id (`member`) resolves the associated
     * HouseholdMember's identifier as an already-database-converted scalar
     * — not the original value object — when building DELETE/UPDATE
     * statements, then passes it back through this type.
     */
    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof MemberId) {
            return $value->value;
        }

        if (is_string($value)) {
            return $value;
        }

        throw new \UnexpectedValueException(sprintf(
            'Expected null, string, or MemberId, got %s.',
            get_debug_type($value),
        ));
    }
}
