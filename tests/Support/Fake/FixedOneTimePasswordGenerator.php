<?php

declare(strict_types=1);

namespace App\Tests\Support\Fake;

use App\Users\Domain\OneTimePasswordGenerator;
use App\Users\Domain\ValueObject\OneTimePassword;

/**
 * Deterministic {@see OneTimePasswordGenerator} for tests: always returns
 * the same credential (or a configured one), so handler tests can assert
 * on the exact plaintext returned.
 */
final class FixedOneTimePasswordGenerator implements OneTimePasswordGenerator
{
    private const string DEFAULT_VALUE = 'aB3dE6fH9jKm';

    private readonly OneTimePassword $value;

    public function __construct(?OneTimePassword $value = null)
    {
        $this->value = $value ?? OneTimePassword::of(self::DEFAULT_VALUE);
    }

    public function generate(): OneTimePassword
    {
        return $this->value;
    }
}
