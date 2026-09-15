<?php

declare(strict_types=1);

namespace App\Households\Infrastructure\Http\Controller;

use App\Households\Application\Query\GetMemberDetail;
use App\Households\Application\Query\Port\MemberDetail;
use LogicException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Throwable;

/**
 * Shared query/command dispatch helpers for the Households HTTP
 * controllers that drive the member detail page and its card mutation
 * flows. Extracted from {@see MemberDetailController} (LRA-207) so
 * {@see MemberPhotoController} does not duplicate the same
 * Messenger-unwrapping boilerplate — new controller classes kept the
 * SonarCloud new-code duplication gate under 3%.
 *
 * Requires the consuming controller to expose `$this->queryBus` and
 * `$this->commandBus` as `MessageBusInterface` properties.
 */
trait DispatchesHouseholdMessages
{
    /**
     * Dispatches the GetMemberDetail query and unwraps Messenger's
     * HandlerFailedException so the original domain exceptions reach the
     * caller. Domain exceptions cannot leak through Messenger's bus in
     * their raw form because handler failures are always wrapped.
     */
    private function runQuery(string $householdId, string $memberId): MemberDetail
    {
        try {
            $envelope = $this->queryBus->dispatch(new GetMemberDetail($householdId, $memberId));
        } catch (HandlerFailedException $wrapper) {
            $nested = $wrapper->getPrevious();
            if ($nested instanceof Throwable) {
                throw $nested;
            }
            throw $wrapper;
        }

        $result = $this->resultOf($envelope);

        if (!$result instanceof MemberDetail) {
            throw new LogicException(sprintf(
                'GetMemberDetail handler returned %s, expected %s.',
                get_debug_type($result),
                MemberDetail::class,
            ));
        }

        return $result;
    }

    /**
     * Dispatch a command through the command bus and unwrap
     * HandlerFailedException so domain exceptions surface to the caller.
     */
    private function dispatchCommandUnwrapping(object $command): void
    {
        try {
            $this->commandBus->dispatch($command);
        } catch (HandlerFailedException $wrapper) {
            $nested = $wrapper->getPrevious();
            if ($nested instanceof Throwable) {
                throw $nested;
            }
            throw $wrapper;
        }
    }

    /**
     * Same contract as {@see self::dispatchCommandUnwrapping()}, for a
     * command handler whose return value the caller needs (LRA-209's
     * SplitMember returns the new MemberId to redirect to).
     */
    private function dispatchCommandUnwrappingWithResult(object $command): mixed
    {
        try {
            $envelope = $this->commandBus->dispatch($command);
        } catch (HandlerFailedException $wrapper) {
            $nested = $wrapper->getPrevious();
            if ($nested instanceof Throwable) {
                throw $nested;
            }
            throw $wrapper;
        }

        return $this->resultOf($envelope);
    }

    /**
     * Extract the single handler result from a dispatched Envelope. Mirrors
     * Messenger's HandleTrait behaviour without coupling the controller to
     * the trait (controllers use both the query bus and the command bus,
     * which the trait cannot multiplex).
     */
    private function resultOf(Envelope $envelope): mixed
    {
        $stamps = $envelope->all(HandledStamp::class);

        if ($stamps === []) {
            throw new LogicException('Dispatched message produced no HandledStamp.');
        }

        if (count($stamps) > 1) {
            throw new LogicException('Dispatched message produced more than one HandledStamp.');
        }

        /** @var HandledStamp $stamp */
        $stamp = $stamps[0];

        return $stamp->getResult();
    }
}
