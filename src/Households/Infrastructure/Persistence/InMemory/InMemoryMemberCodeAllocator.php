<?php

declare(strict_types=1);

namespace App\Households\Infrastructure\Persistence\InMemory;

use App\Households\Domain\MemberCodeAllocator;
use App\Households\Domain\ValueObject\MemberCode;

/**
 * In-memory allocator that emits sequential codes "M000001", "M000002", ...
 * Intended for dev/test wiring; LRA-37 replaces this with a Doctrine-backed
 * adapter that pulls from a Postgres sequence.
 */
final class InMemoryMemberCodeAllocator implements MemberCodeAllocator
{
    private int $counter = 0;

    public function next(): MemberCode
    {
        $this->counter++;

        return MemberCode::of(sprintf('M%06d', $this->counter));
    }
}
