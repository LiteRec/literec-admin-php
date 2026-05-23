<?php

declare(strict_types=1);

namespace App\Tests\Support\Fake;

use App\Users\Domain\IdentityGenerator;
use App\Users\Domain\ValueObject\UserId;
use LogicException;

/**
 * Test double for IdentityGenerator that returns a pre-seeded queue of
 * identifiers in order. Throws when the queue is exhausted so a test that
 * accidentally requests more ids than it set up fails loudly.
 */
final class SequenceIdentityGenerator implements IdentityGenerator
{
    /** @var list<UserId> */
    private array $queue;

    public function __construct(UserId ...$ids)
    {
        // array_values() is required so PHPStan can prove the property's
        // list<UserId> shape; variadics widen to array<int,UserId> in level 9.
        $this->queue = array_values($ids);
    }

    public function nextUserId(): UserId
    {
        if ($this->queue === []) {
            throw new LogicException('Identity queue exhausted.');
        }

        return array_shift($this->queue);
    }
}
