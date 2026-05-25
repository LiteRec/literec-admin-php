<?php

declare(strict_types=1);

namespace App\Tests\Functional\Catalog\Integration;

use App\Catalog\Integration\Event\LineSold;

/**
 * Test-only command: dispatches a LineSold envelope on the event bus
 * with DispatchAfterCurrentBusStamp, then throws to force the
 * command.bus doctrine_transaction middleware to roll back. Used by
 * LineSoldPostCommitTest to prove the post-commit guarantee.
 */
final readonly class FailingPublishCommand
{
    public function __construct(
        public LineSold $event,
    ) {
    }
}
