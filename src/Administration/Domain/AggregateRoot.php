<?php

declare(strict_types=1);

namespace App\Administration\Domain;

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

    /**
     * Non-destructive peek at the buffered events, unlike
     * {@see releaseEvents()} which empties the buffer. Used by
     * {@see \App\Administration\Infrastructure\Persistence\Doctrine\DoctrineRanks}
     * to read the actor behind the change about to be persisted, for the
     * rank-roles join-table columns Doctrine's own change tracking does
     * not cover (see that class's docblock). Reading this and still
     * expecting releaseEvents() to redeliver the same events is fine — it
     * is a read, not a consume.
     *
     * @return list<object>
     */
    final public function pendingEvents(): array
    {
        return $this->pendingEvents;
    }
}
