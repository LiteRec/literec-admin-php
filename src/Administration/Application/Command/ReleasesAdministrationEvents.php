<?php

declare(strict_types=1);

namespace App\Administration\Application\Command;

use App\Administration\Domain\Administrator;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;

/**
 * Shared post-persistence event dispatch for the six Administrator
 * lifecycle command handlers (LRA-279), each of which loads, mutates, and
 * saves exactly one {@see Administrator} aggregate. Extracted so the
 * six otherwise near-identical handlers do not repeat this loop —
 * mirrors {@see \App\Households\Application\Command\ReleasesHouseholdEvents}
 * — and to keep the SonarCloud new-code duplication gate under threshold.
 *
 * Requires the consuming handler to implement {@see self::eventBus()},
 * returning the bus the handler already holds as a constructor-promoted
 * property.
 */
trait ReleasesAdministrationEvents
{
    abstract private function eventBus(): MessageBusInterface;

    private function releaseAndDispatch(Administrator $administrator): void
    {
        foreach ($administrator->releaseEvents() as $event) {
            $this->eventBus()->dispatch($event, [new DispatchAfterCurrentBusStamp()]);
        }
    }
}
