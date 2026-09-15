<?php

declare(strict_types=1);

namespace App\Households\Infrastructure\Persistence\Doctrine\Event;

use App\Households\Domain\Event\MemberSplitOff;
use App\Households\Domain\ValueObject\MemberLineageKind;
use DateTimeZone;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Persists one append-only row in the shared `household_member_lineage`
 * audit table for every {@see MemberSplitOff} domain event dispatched on
 * the `event.bus` (LRA-209). Sibling of
 * {@see RecordMemberMergeLineageHandler}: the new member is
 * `member`/`household`, the source it was split from is
 * `related_member`/`related_household` — the inverse relationship shape
 * of the MERGED_INTO rows written by LRA-208, sharing the same table.
 *
 * `transaction_ids` carries the selected transaction references as a
 * JSON array — the one column LRA-208's writer always leaves NULL.
 */
#[AsMessageHandler(bus: 'event.bus')]
final class RecordMemberSplitLineageHandler
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function __invoke(MemberSplitOff $event): void
    {
        $utc = new DateTimeZone('UTC');

        $this->connection->executeStatement(
            'INSERT INTO household_member_lineage '
            . '(household_id, member_id, related_household_id, related_member_id, kind, reason, '
            . 'transaction_ids, recorded_at) '
            . 'VALUES '
            . '(:household_id, :member_id, :related_household_id, :related_member_id, :kind, :reason, '
            . ':transaction_ids, :recorded_at)',
            [
                'household_id'         => $event->householdId->value,
                'member_id'            => $event->newMemberId->value,
                'related_household_id' => $event->householdId->value,
                'related_member_id'    => $event->sourceMemberId->value,
                'kind'                 => MemberLineageKind::SplitFrom->value,
                'reason'               => $event->reason,
                'transaction_ids'      => json_encode($event->transactions->toStrings(), JSON_THROW_ON_ERROR),
                'recorded_at'          => $event->occurredAt->setTimezone($utc)->format('Y-m-d H:i:s'),
            ],
            [
                'household_id'         => ParameterType::STRING,
                'member_id'            => ParameterType::STRING,
                'related_household_id' => ParameterType::STRING,
                'related_member_id'    => ParameterType::STRING,
                'kind'                 => ParameterType::STRING,
                'reason'               => $event->reason === null ? ParameterType::NULL : ParameterType::STRING,
                'transaction_ids'      => ParameterType::STRING,
                'recorded_at'          => ParameterType::STRING,
            ],
        );
    }
}
