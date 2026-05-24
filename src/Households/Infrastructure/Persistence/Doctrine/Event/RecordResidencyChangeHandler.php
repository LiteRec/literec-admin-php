<?php

declare(strict_types=1);

namespace App\Households\Infrastructure\Persistence\Doctrine\Event;

use App\Households\Domain\Event\MemberResidencyChanged;
use DateTimeZone;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Persists one append-only row in `household_residency_history` for every
 * {@see MemberResidencyChanged} domain event dispatched on the
 * `event.bus` (LRA-44).
 *
 * The history table is an audit log: rows are never updated or deleted by
 * the aggregate. Writing the row from the event handler — rather than
 * inside the aggregate — keeps Household's behaviour pure and lets the
 * read side (or future projections) consume the same event without
 * coupling to Doctrine.
 *
 * Uses {@see Connection} directly rather than the EntityManager: the
 * table has no aggregate counterpart and the handler does a single
 * parameterised INSERT, so there is no value in the UnitOfWork or
 * hydrator path.
 */
#[AsMessageHandler(bus: 'event.bus')]
final class RecordResidencyChangeHandler
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function __invoke(MemberResidencyChanged $event): void
    {
        // Normalise both timestamps to UTC before storing so range queries
        // and cross-timezone comparisons against the history table behave
        // consistently regardless of the originating request's timezone.
        $utc = new DateTimeZone('UTC');

        $this->connection->executeStatement(
            'INSERT INTO household_residency_history '
            . '(household_id, member_id, status, effective_from, reason, recorded_at) '
            . 'VALUES (:household_id, :member_id, :status, :effective_from, :reason, :recorded_at)',
            [
                'household_id'   => $event->householdId->value,
                'member_id'      => $event->memberId->value,
                'status'         => $event->status->value,
                'effective_from' => $event->effectiveFrom->setTimezone($utc)->format('Y-m-d H:i:s'),
                'reason'         => $event->reason,
                'recorded_at'    => $event->occurredAt->setTimezone($utc)->format('Y-m-d H:i:s'),
            ],
            [
                'household_id'   => ParameterType::STRING,
                'member_id'      => ParameterType::STRING,
                'status'         => ParameterType::STRING,
                'effective_from' => ParameterType::STRING,
                'reason'         => $event->reason === null ? ParameterType::NULL : ParameterType::STRING,
                'recorded_at'    => ParameterType::STRING,
            ],
        );
    }
}
