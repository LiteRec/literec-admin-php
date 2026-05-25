<?php

declare(strict_types=1);

namespace App\Tests\Support\Fake;

use App\Catalog\Domain\IdentityGenerator;
use App\Catalog\Domain\ValueObject\ListingId;
use LogicException;

/**
 * Test double for the Catalog IdentityGenerator port that returns a
 * pre-seeded queue of listing ids in order. Throws when the queue is
 * exhausted so tests that accidentally request more ids than they set
 * up fail loudly.
 */
final class SequenceListingIdentityGenerator implements IdentityGenerator
{
    /** @var list<ListingId> */
    private array $queue;

    public function __construct(ListingId ...$ids)
    {
        // array_values() is required so PHPStan can prove the property's
        // list<ListingId> shape; variadics widen to array<int,ListingId>
        // at level 9.
        $this->queue = array_values($ids);
    }

    public function nextListingId(): ListingId
    {
        if ($this->queue === []) {
            throw new LogicException('Listing identity queue exhausted.');
        }

        return array_shift($this->queue);
    }
}
