<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Persistence\Doctrine\Type;

use App\Inventory\Domain\ValueObject\FacilityCode;
use App\Inventory\Domain\ValueObject\FacilityScope;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\JsonType;
use UnexpectedValueException;

/**
 * Persists a {@see FacilityScope} value object as JSONB. The wire
 * shape is `{"isAll": true}` for the all-facilities marker or
 * `{"facilities": ["code1", "code2"]}` for a specific list — the
 * scope reconstructor enforces sort + dedup invariants on hydration
 * so a manually-edited row cannot smuggle a malformed list past the
 * domain factories.
 */
final class FacilityScopeType extends JsonType
{
    public const NAME = 'inventory_facility_scope';

    public function getName(): string
    {
        return self::NAME;
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?FacilityScope
    {
        if ($value === null || $value instanceof FacilityScope) {
            return $value;
        }

        $decoded = parent::convertToPHPValue($value, $platform);

        if (! is_array($decoded)) {
            throw new UnexpectedValueException(sprintf(
                'Expected JSON object for facility scope, got %s.',
                get_debug_type($decoded),
            ));
        }

        if (($decoded['isAll'] ?? false) === true) {
            return FacilityScope::all();
        }

        $facilities = $decoded['facilities'] ?? [];
        if (! is_array($facilities)) {
            throw new UnexpectedValueException(
                'FacilityScope JSON facilities must be a list of strings.',
            );
        }

        $codes = [];
        foreach (array_values($facilities) as $code) {
            if (! is_string($code)) {
                throw new UnexpectedValueException(sprintf(
                    'FacilityScope JSON facilities entries must be strings, got %s.',
                    get_debug_type($code),
                ));
            }
            $codes[] = FacilityCode::fromString($code);
        }

        return FacilityScope::ofFacilities($codes);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if (! $value instanceof FacilityScope) {
            throw new UnexpectedValueException(sprintf(
                'Expected null or FacilityScope, got %s.',
                get_debug_type($value),
            ));
        }

        $payload = $value->isAll
            ? ['isAll' => true]
            : ['facilities' => array_map(
                static fn (FacilityCode $code): string => $code->value,
                $value->facilities,
            )];

        return parent::convertToDatabaseValue($payload, $platform);
    }
}
