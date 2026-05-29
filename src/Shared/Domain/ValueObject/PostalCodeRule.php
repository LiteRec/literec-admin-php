<?php

declare(strict_types=1);

namespace App\Shared\Domain\ValueObject;

/**
 * Shared postal-code format rule.
 *
 * The same per-country validation (US ZIP, CA postal, GB length) and CA
 * uppercasing are needed by every address value object across the bounded
 * contexts (Households {@see Address}, Inventory {@see VendorAddress}). Like
 * {@see EmailAddress} and {@see PhoneNumber}, it is a genuinely reusable domain
 * primitive, so it lives in the shared kernel rather than being copied per
 * context. Each address value object maps a null result to its own context
 * exception.
 */
final class PostalCodeRule
{
    private const US_ZIP_PATTERN = '/^\d{5}(-\d{4})?$/';

    private const CA_POSTAL_PATTERN = '/^[ABCEGHJKLMNPRSTVXY]\d[ABCEGHJKLMNPRSTVWXYZ] \d[ABCEGHJKLMNPRSTVWXYZ]\d$/';

    /**
     * Returns the normalised postal code for storage, or null when it is
     * invalid for the country. CA codes are uppercased; every other country
     * keeps the trimmed input as-is. UK postcodes are ASCII by spec, so strlen
     * is sufficient and avoids depending on ext-mbstring.
     */
    public static function normalize(string $postal, string $country): ?string
    {
        $postal = trim($postal);
        $normalised = $country === 'CA' ? strtoupper($postal) : $postal;

        $valid = match ($country) {
            'US' => preg_match(self::US_ZIP_PATTERN, $normalised) === 1,
            'CA' => preg_match(self::CA_POSTAL_PATTERN, $normalised) === 1,
            'GB' => strlen($normalised) >= 2 && strlen($normalised) <= 10,
            default => true,
        };

        return $valid ? $normalised : null;
    }
}
