<?php

declare(strict_types=1);

namespace App\Users\Application\Command;

/**
 * Staff-initiated command: issue a fresh one-time login credential for a
 * user. The handler generates the plaintext, hashes it, and returns the
 * {@see \App\Users\Domain\ValueObject\OneTimePassword} so the caller
 * (console command today, LRA-213 Part B's admin dialog later) can show it
 * to the operator exactly once.
 */
final readonly class IssueOneTimePassword
{
    public function __construct(
        public string $userId,
    ) {
    }
}
