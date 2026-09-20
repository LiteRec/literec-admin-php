<?php

declare(strict_types=1);

namespace App\Administration\Application\Query;

/**
 * Primitive-only query DTO answering "may whoever holds $subjectRankId
 * act on whoever holds $targetRankId", expressed purely as
 * {@see \App\Administration\Domain\ValueObject\SeniorityLevel::isAtLeastAsSeniorAs()}
 * over the two ranks. Deliberately stateless about privileges — LRA-270
 * composes this with privilege resolution rather than inheriting from it.
 *
 * Takes rank identities rather than administrator identities: the
 * Administrator aggregate (LRA-269) has not landed yet, and this query
 * has no need of it — seniority lives entirely on Rank.
 */
final readonly class SeniorityComparison
{
    public function __construct(
        public string $subjectRankId,
        public string $targetRankId,
    ) {
    }
}
