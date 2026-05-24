<?php

declare(strict_types=1);

namespace App\Households\Application\Query\Port;

/**
 * Paginated result of {@see MemberReadModel::search()}.
 */
final readonly class PageOfMembers
{
    /**
     * @param list<MemberListItem> $items
     */
    public function __construct(
        public array $items,
        public int $page,
        public int $pageSize,
        public int $totalItems,
    ) {
    }

    public function totalPages(): int
    {
        if ($this->totalItems <= 0 || $this->pageSize <= 0) {
            return 0;
        }

        return (int) ceil($this->totalItems / $this->pageSize);
    }
}
