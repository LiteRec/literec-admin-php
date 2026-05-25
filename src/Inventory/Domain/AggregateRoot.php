<?php

declare(strict_types=1);

namespace App\Inventory\Domain;

/**
 * Buffers domain events recorded during aggregate state changes so the
 * application service can release them after the persistence transaction
 * commits.
 *
 * Per-context copy of the Catalog/Households trait: bounded contexts must
 * not share domain types across context boundaries.
 */
trait AggregateRoot
{
    /** @var list<object> */
    private array $pendingEvents = [];

    final protected function recordThat(object $event): void
    {
        $this->pendingEvents[] = $event;
    }

    /**
     * @return list<object>
     */
    final public function releaseEvents(): array
    {
        $events = $this->pendingEvents;
        $this->pendingEvents = [];

        return $events;
    }
}
