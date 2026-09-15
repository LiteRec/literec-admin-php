<?php

declare(strict_types=1);

namespace App\Households\Infrastructure\Persistence\Doctrine\Event;

use App\Households\Domain\Event\MemberAnonymized;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Nulls every free-text `reason` on the anonymized member's
 * `household_residency_history` rows in response to
 * {@see MemberAnonymized} (LRA-212). Staff-typed residency-change reasons
 * can carry PII ("moved in with her mother"), so anonymization must scrub
 * them the same way it scrubs the member's own profile fields — the rows
 * themselves stay (the history table is an audit log; only the free text
 * is PII).
 *
 * Uses {@see Connection} directly rather than the EntityManager, mirroring
 * {@see RecordResidencyChangeHandler}: a single parameterised UPDATE has no
 * use for the UnitOfWork or hydrator path.
 */
#[AsMessageHandler(bus: 'event.bus')]
final class ScrubResidencyHistoryReasonsHandler
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function __invoke(MemberAnonymized $event): void
    {
        $this->connection->executeStatement(
            'UPDATE household_residency_history SET reason = NULL WHERE member_id = :member_id',
            ['member_id' => $event->memberId->value],
            ['member_id' => ParameterType::STRING],
        );
    }
}
