<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Persistence\Doctrine\Type;

use App\Catalog\Domain\ValueObject\TaxTreatment;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\JsonType;
use UnexpectedValueException;

/**
 * Serialises TaxTreatment as a compact JSON object so the two flags are
 * stored atomically without leaking the invariant into the schema (a
 * second case is added with no migration).
 */
final class TaxTreatmentType extends JsonType
{
    public const NAME = 'catalog_tax_treatment';

    public function getName(): string
    {
        return self::NAME;
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?TaxTreatment
    {
        if ($value === null || $value instanceof TaxTreatment) {
            return $value;
        }

        $decoded = parent::convertToPHPValue($value, $platform);

        if (
            ! is_array($decoded)
            || ! array_key_exists('applyTax', $decoded)
            || ! array_key_exists('taxIncludedInFee', $decoded)
        ) {
            throw new UnexpectedValueException(
                'TaxTreatment column did not decode to {applyTax, taxIncludedInFee}.'
            );
        }

        return TaxTreatment::of((bool) $decoded['applyTax'], (bool) $decoded['taxIncludedInFee']);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return parent::convertToDatabaseValue(null, $platform);
        }

        if (! $value instanceof TaxTreatment) {
            throw new UnexpectedValueException(sprintf(
                'Expected null or TaxTreatment, got %s.',
                get_debug_type($value),
            ));
        }

        return parent::convertToDatabaseValue(
            ['applyTax' => $value->applyTax, 'taxIncludedInFee' => $value->taxIncludedInFee],
            $platform,
        );
    }
}
