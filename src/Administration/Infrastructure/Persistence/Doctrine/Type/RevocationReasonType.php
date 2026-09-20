<?php

declare(strict_types=1);

namespace App\Administration\Infrastructure\Persistence\Doctrine\Type;

use App\Administration\Domain\ValueObject\RevocationReason;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use UnexpectedValueException;

/**
 * Maps to a nullable VARCHAR(255) column: unlike {@see RoleDescriptionType},
 * the absent case is a genuine SQL NULL (an open tenure has no revocation
 * reason), never an empty string — see {@see RevocationReason}, which
 * has no empty() constructor.
 */
final class RevocationReasonType extends Type
{
    public const NAME = 'administration_revocation_reason';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL(['length' => RevocationReason::MAX_LENGTH]);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?RevocationReason
    {
        if ($value === null || $value instanceof RevocationReason) {
            return $value;
        }

        if (!is_string($value)) {
            throw new UnexpectedValueException(sprintf(
                'Expected string or RevocationReason, got %s.',
                get_debug_type($value),
            ));
        }

        return RevocationReason::of($value);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof RevocationReason) {
            return $value->value;
        }

        throw new UnexpectedValueException(sprintf(
            'Expected null or RevocationReason, got %s.',
            get_debug_type($value),
        ));
    }
}
