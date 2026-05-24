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

    public ?string $memberCode;
    public ?string $lastName;
    public ?string $firstName;
    public ?string $receipt;
    public ?string $phone;
    public ?string $orgName;
    public ?string $email;
    public ?string $gateway;
    public bool $primaryOnly;
    public bool $includeMerged;
    public bool $includeDeleted;
    public bool $recentOnly;
    public int $page;
    public int $pageSize;

    public function __construct(
        ?string $memberCode = null,
        ?string $lastName = null,
        ?string $firstName = null,
        ?string $receipt = null,
        ?string $phone = null,
        ?string $orgName = null,
        ?string $email = null,
        ?string $gateway = null,
        bool $primaryOnly = false,
        bool $includeMerged = false,
        bool $includeDeleted = false,
        bool $recentOnly = false,
        int $page = 1,
        int $pageSize = 20,
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

        $this->memberCode    = self::nullIfBlank($memberCode);
        $this->lastName      = self::nullIfBlank($lastName);
        $this->firstName     = self::nullIfBlank($firstName);
        $this->receipt       = self::nullIfBlank($receipt);
        $this->phone         = self::nullIfBlank($phone);
        $this->orgName       = self::nullIfBlank($orgName);
        $this->email         = self::nullIfBlank($email);
        $this->gateway       = self::nullIfBlank($gateway);
        $this->primaryOnly   = $primaryOnly;
        $this->includeMerged = $includeMerged;
        $this->includeDeleted = $includeDeleted;
        $this->recentOnly    = $recentOnly;
        $this->page          = $page;
        $this->pageSize      = $pageSize;
    }

    /**
     * Normalises caller input so an empty form field does not become a
     * `LIKE '%%'` filter at the database boundary. Whitespace-only
     * strings are treated as blank.
     */
    private static function nullIfBlank(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $value;
    }
}
