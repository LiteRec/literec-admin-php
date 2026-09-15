<?php

declare(strict_types=1);

namespace App\Households\Infrastructure\Persistence\InMemory;

use App\Households\Application\Query\Port\HouseholdSummary;
use App\Households\Application\Query\Port\LinkedHouseholdDto;
use App\Households\Application\Query\Port\MemberAddressDto;
use App\Households\Application\Query\Port\MemberDetail;
use App\Households\Application\Query\Port\MemberListItem;
use App\Households\Application\Query\Port\MemberProfileDto;
use App\Households\Application\Query\Port\MemberReadModel;
use App\Households\Application\Query\Port\MemberResidencyDto;
use App\Households\Application\Query\Port\MemberSegmentCounts;
use App\Households\Application\Query\Port\MembersSegment;
use App\Households\Application\Query\Port\PageOfMembers;
use App\Households\Application\Query\Port\SearchMembersCriteria;
use App\Households\Domain\Exception\MemberNotFound;
use App\Households\Domain\Household;
use App\Households\Domain\HouseholdMember;
use App\Households\Domain\ValueObject\HouseholdId;
use App\Households\Domain\ValueObject\MemberId;
use App\Households\Domain\ValueObject\ResidencyStatus;
use DateTimeInterface;

/**
 * In-memory adapter for the {@see MemberReadModel} port. Walks aggregate
 * instances in memory and projects them to the same DTOs the Doctrine
 * adapter returns from SQL, so domain/application unit tests can stay
 * #[Small] without booting Doctrine.
 *
 * The orgName, gateway, and recentOnly criteria fields are accepted but
 * ignored (see {@see SearchMembersCriteria} for the rationale). includeMerged
 * (LRA-208) is honoured: merged members are excluded by default from every
 * result set (search, segmentCounts) and included only when set.
 */
final class InMemoryMemberReadModel implements MemberReadModel
{
    /** @var array<string, Household> indexed by HouseholdId->value */
    private array $households = [];

    public function withHousehold(Household $household): self
    {
        $this->households[$household->id()->value] = $household;

        return $this;
    }

    public function search(SearchMembersCriteria $criteria): PageOfMembers
    {
        /** @var list<array{member: HouseholdMember, household: Household}> $rows */
        $rows = [];
        foreach ($this->households as $household) {
            foreach ($household->members() as $member) {
                if (!$this->matches($member, $household, $criteria)) {
                    continue;
                }
                $rows[] = ['member' => $member, 'household' => $household];
            }
        }

        usort($rows, static function (array $a, array $b): int {
            $byLast = strcasecmp($a['member']->name()->lastName, $b['member']->name()->lastName);
            if ($byLast !== 0) {
                return $byLast;
            }

            $byFirst = strcasecmp($a['member']->name()->firstName, $b['member']->name()->firstName);
            if ($byFirst !== 0) {
                return $byFirst;
            }

            // Final deterministic tie-breaker by member id, matching the
            // Doctrine adapter's `ORDER BY ..., m.id ASC` clause.
            return strcmp($a['member']->id()->value, $b['member']->id()->value);
        });

        $total = count($rows);
        $offset = ($criteria->page - 1) * $criteria->pageSize;
        $slice = array_slice($rows, $offset, $criteria->pageSize);

        $items = [];
        foreach ($slice as $row) {
            $items[] = $this->toListItem($row['member'], $row['household']);
        }

        return new PageOfMembers(
            $items,
            $criteria->page,
            $criteria->pageSize,
            $total,
        );
    }

    public function segmentCounts(?string $q): MemberSegmentCounts
    {
        $all = 0;
        $residents = 0;
        $nonResidents = 0;
        $inactive = 0;

        foreach ($this->households as $household) {
            foreach ($household->members() as $member) {
                if ($this->excludedFromSegmentCounts($member, $household, $q)) {
                    continue;
                }

                if (!$member->isActive()) {
                    $inactive++;
                    continue;
                }

                $all++;
                if ($member->residencyStatus() === ResidencyStatus::Resident) {
                    $residents++;
                } elseif ($member->residencyStatus() === ResidencyStatus::NonResident) {
                    $nonResidents++;
                }
            }
        }

        return new MemberSegmentCounts($all, $residents, $nonResidents, $inactive);
    }

    /**
     * Merged members are always excluded from the segment counts; the
     * remainder are narrowed to the free-text `q` term when supplied.
     * Split out of {@see self::segmentCounts()} to keep that method's
     * nesting (and cognitive complexity) under the SonarCloud threshold.
     */
    private function excludedFromSegmentCounts(HouseholdMember $member, Household $household, ?string $q): bool
    {
        return $member->isMerged() || ($q !== null && !$this->matchesQuery($member, $household, $q));
    }

    /**
     * $householdId names the household being VIEWED, which may differ from
     * the member's home household when the member is a shared minor
     * (LRA-210) viewed from a linked household — `household`, `address`,
     * and `householdMembers` all reflect the viewed household;
     * `homeHouseholdId` names the household that actually owns the
     * member's identity data.
     */
    public function memberDetail(HouseholdId $householdId, MemberId $memberId): MemberDetail
    {
        $viewedHousehold = $this->households[$householdId->value] ?? null;
        if ($viewedHousehold === null) {
            throw MemberNotFound::inHousehold($householdId, $memberId);
        }

        $found = $this->findHomeHouseholdAndMember($memberId);
        if ($found === null) {
            throw MemberNotFound::inHousehold($householdId, $memberId);
        }
        [$homeHousehold, $member] = $found;

        $isHome = $homeHousehold->id()->equals($householdId);
        if (!$isHome && !$member->isSharedWith($householdId)) {
            throw MemberNotFound::inHousehold($householdId, $memberId);
        }

        return new MemberDetail(
            $this->householdSummary($viewedHousehold),
            $this->profile($member),
            $this->address($viewedHousehold),
            new MemberResidencyDto($member->residencyStatus()->value, null),
            $this->householdMembers($viewedHousehold),
            $homeHousehold->id()->value,
            $this->linkedHouseholds($member, $homeHousehold),
        );
    }

    /**
     * Finds the household that owns (home) $memberId and the member itself,
     * by walking every aggregate's own members collection — a member only
     * ever appears in its home household's `Household::$members`, so the
     * first (only) match is the home household by construction.
     *
     * @return array{0: Household, 1: HouseholdMember}|null
     */
    private function findHomeHouseholdAndMember(MemberId $memberId): ?array
    {
        foreach ($this->households as $household) {
            foreach ($household->members() as $candidate) {
                if ($candidate->id()->equals($memberId)) {
                    return [$household, $candidate];
                }
            }
        }

        return null;
    }

    /**
     * @return list<LinkedHouseholdDto>
     */
    private function linkedHouseholds(HouseholdMember $member, Household $homeHousehold): array
    {
        $items = [new LinkedHouseholdDto($homeHousehold->id()->value, $homeHousehold->name()->value, true, null)];

        foreach ($member->sharedHouseholdIds() as $sharedId) {
            $sharedHousehold = $this->households[$sharedId->value] ?? null;
            $linkedAt = $member->linkedAtFor($sharedId);
            $items[] = new LinkedHouseholdDto(
                $sharedId->value,
                $sharedHousehold?->name()->value ?? '',
                false,
                $linkedAt?->format(DateTimeInterface::ATOM),
            );
        }

        return $items;
    }

    /**
     * Projects every member of the household — its own (home) members plus
     * every member another household's home aggregate has shared with it
     * (LRA-210) — to a {@see MemberListItem} (active and deactivated
     * alike), sorted by lastName / firstName / id to match the Doctrine
     * adapter's ORDER BY. Powers the Household card roster (LRA-42).
     *
     * @return list<MemberListItem>
     */
    private function householdMembers(Household $household): array
    {
        /** @var list<array{member: HouseholdMember, home: Household, isShared: bool}> $rows */
        $rows = [];
        foreach ($household->members() as $member) {
            $rows[] = ['member' => $member, 'home' => $household, 'isShared' => false];
        }
        foreach ($this->households as $other) {
            if ($other->id()->equals($household->id())) {
                continue;
            }
            foreach ($other->members() as $member) {
                if ($member->isSharedWith($household->id())) {
                    $rows[] = ['member' => $member, 'home' => $other, 'isShared' => true];
                }
            }
        }

        usort($rows, static function (array $a, array $b): int {
            $byLast = strcasecmp($a['member']->name()->lastName, $b['member']->name()->lastName);
            if ($byLast !== 0) {
                return $byLast;
            }
            $byFirst = strcasecmp($a['member']->name()->firstName, $b['member']->name()->firstName);
            if ($byFirst !== 0) {
                return $byFirst;
            }

            return strcmp($a['member']->id()->value, $b['member']->id()->value);
        });

        $items = [];
        foreach ($rows as $row) {
            $items[] = $this->toListItem($row['member'], $row['home'], $row['isShared']);
        }

        return $items;
    }

    private function matches(HouseholdMember $member, Household $household, SearchMembersCriteria $c): bool
    {
        $activeMatches = $c->segment === MembersSegment::Inactive
            ? !$member->isActive()
            : ($c->includeDeleted || $member->isActive());

        $segmentMatches = match ($c->segment) {
            MembersSegment::Residents => $member->residencyStatus() === ResidencyStatus::Resident,
            MembersSegment::NonResidents => $member->residencyStatus() === ResidencyStatus::NonResident,
            default => true,
        };

        return ($c->includeMerged || !$member->isMerged())
            && $activeMatches
            && $segmentMatches
            && ($c->q === null || $this->matchesQuery($member, $household, $c->q))
            && $this->matchesDetailedFilters($member, $c);
    }

    /**
     * The "More filters" fields (LRA-192): each is satisfied when it is
     * unset (null/false) or the member matches it. receipt, orgName,
     * gateway, and recentOnly have no backing data on the in-memory
     * aggregate yet and are intentionally ignored. Split out of
     * {@see self::matches()} to keep that method's cognitive complexity
     * under the SonarCloud threshold.
     */
    private function matchesDetailedFilters(HouseholdMember $member, SearchMembersCriteria $c): bool
    {
        return (!$c->primaryOnly || $member->isPrimary())
            && ($c->memberCode === null || $member->code()->value === $c->memberCode)
            && ($c->lastName === null || stripos($member->name()->lastName, $c->lastName) !== false)
            && ($c->firstName === null || stripos($member->name()->firstName, $c->firstName) !== false)
            && ($c->email === null || $this->valueContains($member->email()?->value, $c->email))
            && ($c->phone === null || $this->valueContains($member->phone()?->value, $c->phone));
    }

    /**
     * The Users list free-text search (LRA-192): matches last name, first
     * name, member code, household name, or phone. Deliberately excludes
     * email — the detailed "More filters" field covers that.
     */
    private function matchesQuery(HouseholdMember $member, Household $household, string $q): bool
    {
        return $this->valueContains($member->name()->lastName, $q)
            || $this->valueContains($member->name()->firstName, $q)
            || $this->valueContains($member->code()->value, $q)
            || $this->valueContains($household->name()->value, $q)
            || $this->valueContains($member->phone()?->value, $q);
    }

    /**
     * Case-insensitive substring match that treats a missing (null) value
     * as a non-match. Uses mb_stripos() (not stripos(), which is byte-wise
     * and misfolds non-ASCII case pairs like "Ü"/"ü") so this stays
     * consistent with the Doctrine adapter's Postgres LOWER() comparison
     * (LRA-192 review).
     */
    private function valueContains(?string $haystack, string $needle): bool
    {
        return $haystack !== null && mb_stripos($haystack, $needle) !== false;
    }

    private function toListItem(HouseholdMember $member, Household $household, bool $isShared = false): MemberListItem
    {
        return new MemberListItem(
            $member->id()->value,
            $household->id()->value,
            $household->name()->value,
            $member->code()->value,
            $member->name()->fullName(),
            $member->email()?->value,
            $member->dateOfBirth()->value->format('Y-m-d'),
            $member->phone()?->value,
            $this->shortAddress($household),
            $member->residencyStatus()->value,
            $member->isPrimary(),
            $member->isActive(),
            $member->photo()?->version(),
            $member->isMerged(),
            $isShared,
        );
    }

    /**
     * memberCount includes both the household's own members and members
     * shared into it from another household's home aggregate (LRA-210), so
     * it matches the union {@see self::householdMembers()} returns.
     */
    private function householdSummary(Household $household): HouseholdSummary
    {
        $members = $household->members();
        $primary = null;
        foreach ($members as $member) {
            if ($member->isPrimary()) {
                $primary = $member;
                break;
            }
        }

        if ($primary === null) {
            // Households always have a primary at registration. An empty
            // members list, or a non-empty list with no member marked
            // primary, is a programming error in the aggregate — throw
            // an explicit exception so tests catch the invariant breach
            // instead of silently fabricating a primary or hitting an
            // undefined offset.
            if ($members === []) {
                throw new \LogicException(
                    'HouseholdSummary requested for a household with no members.',
                );
            }
            throw new \LogicException(
                'HouseholdSummary requested for a household with no primary member.',
            );
        }

        return new HouseholdSummary(
            $household->id()->value,
            $household->name()->value,
            count($members) + $this->countMembersSharedInto($household->id()),
            $primary->id()->value,
            $primary->name()->fullName(),
        );
    }

    private function countMembersSharedInto(HouseholdId $householdId): int
    {
        $count = 0;
        foreach ($this->households as $household) {
            foreach ($household->members() as $member) {
                if ($member->isSharedWith($householdId)) {
                    $count++;
                }
            }
        }

        return $count;
    }

    private function profile(HouseholdMember $member): MemberProfileDto
    {
        $deactivation = $member->deactivation();
        $photo = $member->photo();
        $merge = $member->merge();

        return new MemberProfileDto(
            $member->id()->value,
            $member->code()->value,
            $member->name()->firstName,
            $member->name()->middleName,
            $member->name()->lastName,
            $member->name()->suffix,
            $member->name()->fullName(),
            $member->dateOfBirth()->value->format('Y-m-d'),
            $member->gender()->value,
            $member->email()?->value,
            $member->phone()?->value,
            $member->name()->nickname,
            $member->salutation()?->value,
            $member->height()?->inches,
            $member->weight()?->pounds,
            $member->isPrimary(),
            $member->isActive(),
            $deactivation?->reason,
            $deactivation?->at->format(\DateTimeInterface::ATOM),
            $photo?->version(),
            $photo?->format->value,
            $merge?->intoMemberId->value,
            $merge !== null ? $this->householdIdFor($merge->intoMemberId) : null,
            $merge?->at->format(\DateTimeInterface::ATOM),
        );
    }

    /**
     * Resolves the owning household id for a survivor member id, used to
     * project {@see MemberProfileDto::$mergedIntoHouseholdId} on a merged
     * member's profile. Mirrors the Doctrine adapter's LEFT JOIN against
     * household_members for the same projection.
     */
    private function householdIdFor(MemberId $memberId): ?string
    {
        foreach ($this->households as $household) {
            foreach ($household->members() as $candidate) {
                if ($candidate->id()->equals($memberId)) {
                    return $household->id()->value;
                }
            }
        }

        return null;
    }

    private function address(Household $household): MemberAddressDto
    {
        $a = $household->address();

        return new MemberAddressDto(
            $a->street,
            $a->unit,
            $a->city,
            $a->state,
            $a->postalCode,
            $a->country,
        );
    }

    private function shortAddress(Household $household): string
    {
        $a = $household->address();

        return sprintf('%s, %s %s', $a->street, $a->city, $a->state);
    }
}
