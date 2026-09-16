<?php

declare(strict_types=1);

namespace App\Households\Application\Command;

use App\Households\Domain\Household;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;

/**
 * Shared post-persistence event dispatch for command handlers that save a
 * single {@see Household} aggregate. Extracted for
 * {@see LinkMinorToHouseholdHandler} and {@see WithdrawMinorFromHouseholdHandler}
 * (LRA-210), which are otherwise near-identical, to keep the SonarCloud
 * new-code duplication gate under threshold.
 *
 * Requires the consuming handler to implement {@see self::eventBus()},
 * returning the bus the handler already holds as a constructor-promoted
 * property.
 */
trait ReleasesHouseholdEvents
{
    abstract private function eventBus(): MessageBusInterface;

    private function releaseAndDispatch(Household $household): void
    {
        foreach ($household->releaseEvents() as $event) {
            $this->eventBus()->dispatch($event, [new DispatchAfterCurrentBusStamp()]);
        }
    }
}
