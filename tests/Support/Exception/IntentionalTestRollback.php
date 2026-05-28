<?php

declare(strict_types=1);

namespace App\Tests\Support\Exception;

use RuntimeException;

/**
 * Thrown by test-only message handlers that deliberately fail after
 * dispatching a nested message, so a test can assert that the
 * surrounding doctrine_transaction middleware rolls the whole envelope
 * back. Carrying a dedicated type keeps the intent explicit and lets
 * tests catch exactly this failure rather than a bare RuntimeException.
 */
final class IntentionalTestRollback extends RuntimeException
{
    public static function create(): self
    {
        return new self('Intentional rollback triggered by a test fixture.');
    }
}
