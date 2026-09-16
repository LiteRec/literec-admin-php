<?php

declare(strict_types=1);

namespace App\Fixture\Application\Command;

use DateTime;
use DateTimeImmutable;
use DateTimeZone;
use Symfony\Component\Clock\DatePoint;

final class ReceivePurchaseOrderLineCommand
{
    public function __construct(public readonly string $receivedAtIso)
    {
    }
}

final class ReceivePurchaseOrderLineHandler
{
    /**
     * @return list<DateTime|DateTimeImmutable>
     */
    public function __invoke(ReceivePurchaseOrderLineCommand $command): array
    {
        $ambientImmutable = new DateTimeImmutable();
        $ambientMutable = new DateTime('now');
        $ambientNamedArg = new DateTimeImmutable(datetime: 'now');
        $ambientToday = new DateTimeImmutable('today');
        $ambientRelative = new DateTimeImmutable('+1 day');
        $ambientEmptyString = new DateTimeImmutable('');
        $ambientTimezoneOnly = new DateTimeImmutable(timezone: new DateTimeZone('UTC'));
        $ambientDatePoint = new DatePoint();
        $parsedFromCommand = new DateTimeImmutable($command->receivedAtIso);
        $parsedLiteralDate = new DateTimeImmutable('2026-01-01');
        $parsedIso8601 = new DateTimeImmutable('2026-01-01T00:00:00+00:00');

        return [
            $ambientImmutable, $ambientMutable, $ambientNamedArg, $ambientToday, $ambientRelative,
            $ambientEmptyString, $ambientTimezoneOnly, $ambientDatePoint, $parsedFromCommand,
            $parsedLiteralDate, $parsedIso8601,
        ];
    }
}

namespace App\Fixture\Infrastructure\Fixtures;

use DateTimeImmutable;

/**
 * Infrastructure is allowed to touch the ambient clock; only Domain and
 * Application are gated, so this call is not reported.
 */
final class FixedClock
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable();
    }
}
