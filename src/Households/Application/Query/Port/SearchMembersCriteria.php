<?php

declare(strict_types=1);

namespace App\Households\Application\Query\Port;

use InvalidArgumentException;

/**
 * Search criteria for {@see MemberReadModel::search()}.
 *
 * Primitive-only DTO. Every filter field is optional except pagination, which
 * is bounded to protect the database — pageSize is clamped to [1, 100] and
 * page must be >= 1; out-of-range values throw immediately at the boundary.
 *
 * A handful of fields are accepted for call-site stability with the LRA-39
 * members list page but are currently ignored by every adapter because the
 * backing data is not yet present:
 *   - orgName       — no organization-name column exists on members yet.
 *   - gateway       — no membership-gateway column exists yet.
 *   - includeMerged — household_members has no merged flag yet.
 *   - recentOnly    — household_members has no modified_at timestamp yet.
 * Accepting these now means the call sites in LRA-39 / LRA-46 do not have
 * to be re-shaped when the backing data lands.
 */
final readonly class SearchMembersCriteria
{
    public const int MIN_PAGE = 1;
    public const int MIN_PAGE_SIZE = 1;
    public const int MAX_PAGE_SIZE = 100;

    public function __construct(
        public ?string $memberCode = null,
        public ?string $lastName = null,
        public ?string $firstName = null,
        public ?string $receipt = null,
        public ?string $phone = null,
        public ?string $orgName = null,
        public ?string $email = null,
        public ?string $gateway = null,
        public bool $primaryOnly = false,
        public bool $includeMerged = false,
        public bool $includeDeleted = false,
        public bool $recentOnly = false,
        public int $page = 1,
        public int $pageSize = 20,
    ) {
        if ($page < self::MIN_PAGE) {
            throw new InvalidArgumentException(
                sprintf('SearchMembersCriteria: page must be >= %d.', self::MIN_PAGE),
            );
        }

        if ($pageSize < self::MIN_PAGE_SIZE || $pageSize > self::MAX_PAGE_SIZE) {
            throw new InvalidArgumentException(
                sprintf(
                    'SearchMembersCriteria: pageSize must be in [%d, %d].',
                    self::MIN_PAGE_SIZE,
                    self::MAX_PAGE_SIZE,
                ),
            );
        }
    }
}
