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
 * Requires the consuming handler to expose `$this->eventBus` as a
 * {@see MessageBusInterface} property.
 */
trait ReleasesHouseholdEvents
{
    private function releaseAndDispatch(Household $household): void
    {
        foreach ($household->releaseEvents() as $event) {
            $this->eventBus->dispatch($event, [new DispatchAfterCurrentBusStamp()]);
        }
    }
}
