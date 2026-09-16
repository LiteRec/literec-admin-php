<?php

declare(strict_types=1);

namespace App\Fixture\Application\Command;

use DateTime;
use DateTimeImmutable;

final class ReceivePurchaseOrderLineCommand
{
    public function __construct(public readonly string $receivedAtIso)
    {
    }
}

final class ReceivePurchaseOrderLineHandler
{
    public function __invoke(ReceivePurchaseOrderLineCommand $command): void
    {
        $ambientImmutable = new DateTimeImmutable();
        $ambientMutable = new DateTime('now');
        $ambientNamedArg = new DateTimeImmutable(datetime: 'now');
        $parsedFromCommand = new DateTimeImmutable($command->receivedAtIso);
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
