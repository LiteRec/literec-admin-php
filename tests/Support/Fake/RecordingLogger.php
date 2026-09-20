<?php

declare(strict_types=1);

namespace App\Tests\Support\Fake;

use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;
use Stringable;

/**
 * In-memory {@see LoggerInterface} fake collecting every call as a
 * {level, message, context} record. psr/log 3.0.2 no longer ships
 * TestLogger, and the project prefers fakes over mocks, so this stands
 * in for it wherever a test needs to assert something was logged.
 */
final class RecordingLogger implements LoggerInterface
{
    use LoggerTrait;

    /** @var list<array{level: mixed, message: string, context: array<string, mixed>}> */
    private array $records = [];

    /**
     * @param array<string, mixed> $context
     */
    public function log($level, string|Stringable $message, array $context = []): void
    {
        $this->records[] = [
            'level' => $level,
            'message' => (string) $message,
            'context' => $context,
        ];
    }

    /**
     * @return list<array{level: mixed, message: string, context: array<string, mixed>}>
     */
    public function records(): array
    {
        return $this->records;
    }

    public function hasRecordMatching(string $level, string $message): bool
    {
        foreach ($this->records as $record) {
            if ($record['level'] === $level && $record['message'] === $message) {
                return true;
            }
        }

        return false;
    }
}
