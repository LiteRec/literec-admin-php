<?php

declare(strict_types=1);

namespace App\Households\Infrastructure\Persistence\Doctrine;

use App\Households\Domain\MemberCodeAllocator;
use App\Households\Domain\ValueObject\MemberCode;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Doctrine adapter for the {@see MemberCodeAllocator} port.
 *
 * Backed by the Postgres sequence "household_member_code_seq" created
 * in the LRA-37 migration. A sequence is used rather than a
 * counter-row UPDATE because:
 *
 *   - Concurrent allocations across requests must never collide.
 *     `SELECT nextval('seq')` is guaranteed atomic and monotonic in
 *     Postgres regardless of transaction visibility.
 *   - A counter row would require either SERIALIZABLE isolation or an
 *     explicit row lock, both of which serialize unrelated traffic on
 *     the row and create deadlock risk under load.
 *
 * Sequence values are formatted as `M{number:06d}` (e.g. "M000001")
 * to satisfy {@see MemberCode}'s allowed-character contract.
 */
final class DoctrineMemberCodeAllocator implements MemberCodeAllocator
{
    private const SEQUENCE_NAME = 'household_member_code_seq';

    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function next(): MemberCode
    {
        $next = $this->em->getConnection()->fetchOne(
            sprintf("SELECT nextval('%s')", self::SEQUENCE_NAME),
        );

        if ($next === false) {
            throw MemberCodeAllocationFailed::sequenceReturnedNoValue(self::SEQUENCE_NAME);
        }

        if (!is_numeric($next)) {
            throw new \UnexpectedValueException(sprintf(
                'Expected numeric sequence value, got %s.',
                get_debug_type($next),
            ));
        }

        return MemberCode::of(sprintf('M%06d', (int) $next));
    }
}
