<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Persistence\Doctrine\Type;

use App\Catalog\Domain\ValueObject\Currency;
use App\Catalog\Domain\ValueObject\Fee;
use App\Catalog\Domain\ValueObject\Money;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\JsonType;
use UnexpectedValueException;

/**
 * Serialises a list<Fee> as a JSON array of {cents, currency, label}
 * objects so the aggregate boundary stays clean — fees are nested value
 * objects on the Listing aggregate, not a child entity table.
 */
final class FeesType extends JsonType
{
    public const NAME = 'catalog_fees';

    public function getName(): string
    {
        return self::NAME;
    }

    /**
     * @return list<Fee>
     */
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): array
    {
        if ($value === null) {
            return [];
        }

        $decoded = parent::convertToPHPValue($value, $platform);

        if (! is_array($decoded)) {
            throw new UnexpectedValueException('Fees column did not decode to a list.');
        }

        $fees = [];
        foreach ($decoded as $entry) {
            if (
                ! is_array($entry)
                || ! array_key_exists('cents', $entry)
                || ! array_key_exists('currency', $entry)
                || ! array_key_exists('label', $entry)
            ) {
                throw new UnexpectedValueException(
                    'Fees entry expected to be {cents, currency, label}.'
                );
            }

            $cents = $entry['cents'];
            $currency = $entry['currency'];
            $label = $entry['label'];

            if (! is_int($cents) || ! is_string($currency) || ! is_string($label)) {
                throw new UnexpectedValueException('Fees entry has unexpected member types.');
            }

            $fees[] = Fee::of(Money::ofCents($cents, Currency::from($currency)), $label);
        }

        return $fees;
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return parent::convertToDatabaseValue(null, $platform);
        }

        if (! is_array($value)) {
            throw new UnexpectedValueException(sprintf(
                'Expected list<Fee> or null, got %s.',
                get_debug_type($value),
            ));
        }

        $rows = [];
        foreach ($value as $fee) {
            if (! $fee instanceof Fee) {
                throw new UnexpectedValueException(sprintf(
                    'Fees list entry expected to be Fee, got %s.',
                    get_debug_type($fee),
                ));
            }
            $rows[] = [
                'cents'    => $fee->amount->cents,
                'currency' => $fee->amount->currency->value,
                'label'    => $fee->label,
            ];
        }

        return parent::convertToDatabaseValue($rows, $platform);
    }
}
