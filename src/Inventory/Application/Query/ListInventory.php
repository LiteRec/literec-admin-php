<?php

declare(strict_types=1);

namespace App\Inventory\Application\Query;

/**
 * Primitive criteria DTO for the LRA-85 Inventory list page. Empty
 * filter fields mean "no filter applied" on that dimension; an empty
 * search string matches everything.
 *
 * sort is one of: 'name', 'code', 'quantity' (default 'name'). Each
 * may be prefixed with '-' for descending order (e.g., '-quantity'
 * for largest stock first).
 */
final readonly class ListInventory
{
    public function __construct(
        public string $search = '',
        public ?string $facilityCode = null,
        public ?string $groupId = null,
        public ?string $kind = null,
        public ?bool $archived = null,
        public string $sort = 'name',
        public int $pageNumber = 1,
        public int $pageSize = 50,
    ) {
    }
}
