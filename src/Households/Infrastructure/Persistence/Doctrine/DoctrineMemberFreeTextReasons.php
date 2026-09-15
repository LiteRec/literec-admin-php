<?php

declare(strict_types=1);

namespace App\Households\Infrastructure\Persistence\Doctrine;

use App\Households\Domain\MemberFreeTextReasons;
use App\Households\Domain\ValueObject\MemberId;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

/**
 * Doctrine adapter for the {@see MemberFreeTextReasons} port (LRA-212).
 * Nulls every free-text `reason` the member appears in, across
 * `household_residency_history` and `household_member_lineage`. Both
 * tables are append-only audit logs; only the free text is PII, so the
 * rows themselves stay.
 *
 * `household_member_lineage` rows are matched on either `member_id` or
 * `related_member_id`: a split-off row's `related_member_id` is the
 * source the new member was split from
 * ({@see \App\Households\Infrastructure\Persistence\Doctrine\Event\RecordMemberSplitLineageHandler}),
 * so the reason must be cleared regardless of which side of the row the
 * anonymized member is on.
 *
 * Uses {@see Connection} directly rather than the EntityManager, mirroring
 * {@see \App\Households\Infrastructure\Persistence\Doctrine\Event\RecordResidencyChangeHandler}:
 * parameterised UPDATEs have no use for the UnitOfWork or hydrator path,
 * and this connection is the same one the enclosing `command.bus`
 * `doctrine_transaction` middleware already has open, so both statements
 * commit or roll back atomically with the aggregate write.
 */
final class DoctrineMemberFreeTextReasons implements MemberFreeTextReasons
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function scrubFor(MemberId $memberId): void
    {
        $this->connection->executeStatement(
            'UPDATE household_residency_history SET reason = NULL WHERE member_id = :member_id',
            ['member_id' => $memberId->value],
            ['member_id' => ParameterType::STRING],
        );

        $this->connection->executeStatement(
            'UPDATE household_member_lineage SET reason = NULL '
            . 'WHERE member_id = :member_id OR related_member_id = :member_id',
            ['member_id' => $memberId->value],
            ['member_id' => ParameterType::STRING],
        );
    }
}
