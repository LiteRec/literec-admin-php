<?php

declare(strict_types=1);

namespace App\Households\Domain;

use App\Households\Domain\ValueObject\MemberCode;

/**
 * Domain port for allocating the next human-readable membership code.
 * Implementations may pull from a database sequence (Doctrine adapter) or
 * an in-memory counter (test/dev adapter).
 */
interface MemberCodeAllocator
{
    public function next(): MemberCode;
}
