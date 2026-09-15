<?php

declare(strict_types=1);

namespace App\Households\Infrastructure\Persistence\Doctrine\Event;

use App\Households\Domain\Event\MemberMergedInto;
use App\Households\Domain\ValueObject\MemberLineageKind;
use DateTimeZone;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Persists one append-only row in the shared `household_member_lineage`
 * audit table for every {@see MemberMergedInto} domain event dispatched
 * on the `event.bus` (LRA-208). Mirrors
 * {@see RecordResidencyChangeHandler}: the table is an audit log written
 * from the event handler rather than the aggregate, so Household's
 * behaviour stays pure.
 *
 * The duplicate is `member`/`household`; the survivor is
 * `related_member`/`related_household` — matching the table's shared
 * contract with LRA-209 (split), which writes the inverse relationship
 * as SPLIT_FROM rows. `transaction_ids` is left NULL: it is reserved for
 * LRA-209's own writer.
 */
#[AsMessageHandler(bus: 'event.bus')]
final class RecordMemberMergeLineageHandler
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function __invoke(MemberMergedInto $event): void
    {
        $utc = new DateTimeZone('UTC');

        $this->connection->executeStatement(
            'INSERT INTO household_member_lineage '
            . '(household_id, member_id, related_household_id, related_member_id, kind, reason, recorded_at) '
            . 'VALUES '
            . '(:household_id, :member_id, :related_household_id, :related_member_id, :kind, :reason, :recorded_at)',
            [
                'household_id'         => $event->householdId->value,
                'member_id'            => $event->memberId->value,
                'related_household_id' => $event->survivorHouseholdId->value,
                'related_member_id'    => $event->survivorMemberId->value,
                'kind'                 => MemberLineageKind::MergedInto->value,
                'reason'               => null,
                'recorded_at'          => $event->occurredAt->setTimezone($utc)->format('Y-m-d H:i:s'),
            ],
            [
                'household_id'         => ParameterType::STRING,
                'member_id'            => ParameterType::STRING,
                'related_household_id' => ParameterType::STRING,
                'related_member_id'    => ParameterType::STRING,
                'kind'                 => ParameterType::STRING,
                'reason'               => ParameterType::NULL,
                'recorded_at'          => ParameterType::STRING,
            ],
        );
    }
}
