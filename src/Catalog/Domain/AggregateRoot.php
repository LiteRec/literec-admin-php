<?php

declare(strict_types=1);

namespace App\Catalog\Domain;

/**
 * Buffers domain events recorded during aggregate state changes so the
 * application service can release them after the persistence transaction
 * commits.
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
