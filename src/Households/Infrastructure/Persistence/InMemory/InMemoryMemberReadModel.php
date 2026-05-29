<?php

declare(strict_types=1);

namespace App\Households\Infrastructure\Persistence\InMemory;

use App\Households\Application\Query\Port\HouseholdSummary;
use App\Households\Application\Query\Port\MemberAddressDto;
use App\Households\Application\Query\Port\MemberDetail;
use App\Households\Application\Query\Port\MemberListItem;
use App\Households\Application\Query\Port\MemberProfileDto;
use App\Households\Application\Query\Port\MemberReadModel;
use App\Households\Application\Query\Port\MemberResidencyDto;
use App\Households\Application\Query\Port\PageOfMembers;
use App\Households\Application\Query\Port\SearchMembersCriteria;
use App\Households\Domain\Exception\MemberNotFound;
use App\Households\Domain\Household;
use App\Households\Domain\HouseholdMember;
use App\Households\Domain\ValueObject\HouseholdId;
use App\Households\Domain\ValueObject\MemberId;

/**
 * In-memory adapter for the {@see MemberReadModel} port. Walks aggregate
 * instances in memory and projects them to the same DTOs the Doctrine
 * adapter returns from SQL, so domain/application unit tests can stay
 * #[Small] without booting Doctrine.
 *
 * The orgName, gateway, includeMerged, and recentOnly criteria fields
 * are accepted but ignored (see {@see SearchMembersCriteria} for the
 * rationale).
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
                if (!$this->matches($member, $criteria)) {
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

    public function memberDetail(HouseholdId $householdId, MemberId $memberId): MemberDetail
    {
        $household = $this->households[$householdId->value] ?? null;
        if ($household === null) {
            throw MemberNotFound::inHousehold($householdId, $memberId);
        }

        $member = null;
        foreach ($household->members() as $candidate) {
            if ($candidate->id()->equals($memberId)) {
                $member = $candidate;
                break;
            }
        }

        if ($member === null) {
            throw MemberNotFound::inHousehold($householdId, $memberId);
        }

        return new MemberDetail(
            $this->householdSummary($household),
            $this->profile($member),
            $this->address($household),
            new MemberResidencyDto($member->residencyStatus()->value, null),
            $this->householdMembers($household),
        );
    }

    /**
     * Projects every member of the household to a {@see MemberListItem}
     * (active and deactivated alike), sorted by lastName / firstName / id
     * to match the Doctrine adapter's ORDER BY. Powers the Household card
     * roster (LRA-42).
     *
     * @return list<MemberListItem>
     */
    private function householdMembers(Household $household): array
    {
        $members = $household->members();

        usort($members, static function (HouseholdMember $a, HouseholdMember $b): int {
            $byLast = strcasecmp($a->name()->lastName, $b->name()->lastName);
            if ($byLast !== 0) {
                return $byLast;
            }
            $byFirst = strcasecmp($a->name()->firstName, $b->name()->firstName);
            if ($byFirst !== 0) {
                return $byFirst;
            }

            return strcmp($a->id()->value, $b->id()->value);
        });

        $items = [];
        foreach ($members as $member) {
            $items[] = $this->toListItem($member, $household);
        }

        return $items;
    }

    private function matches(HouseholdMember $member, SearchMembersCriteria $c): bool
    {
        // Each criterion is satisfied when it is unset (null/false) or the
        // member matches it. receipt, orgName, gateway, includeMerged and
        // recentOnly have no backing data on the in-memory aggregate yet
        // and are intentionally ignored.
        return ($c->includeDeleted || $member->isActive())
            && (!$c->primaryOnly || $member->isPrimary())
            && ($c->memberCode === null || $member->code()->value === $c->memberCode)
            && ($c->lastName === null || stripos($member->name()->lastName, $c->lastName) !== false)
            && ($c->firstName === null || stripos($member->name()->firstName, $c->firstName) !== false)
            && ($c->email === null || $this->valueContains($member->email()?->value, $c->email))
            && ($c->phone === null || $this->valueContains($member->phone()?->value, $c->phone));
    }

    /**
     * Case-insensitive substring match that treats a missing (null) value
     * as a non-match.
     */
    private function valueContains(?string $haystack, string $needle): bool
    {
        return $haystack !== null && stripos($haystack, $needle) !== false;
    }

    private function toListItem(HouseholdMember $member, Household $household): MemberListItem
    {
        return new MemberListItem(
            $member->id()->value,
            $household->id()->value,
            $member->code()->value,
            $member->name()->fullName(),
            $member->dateOfBirth()->value->format('Y-m-d'),
            $member->phone()?->value,
            $this->shortAddress($household),
            $member->residencyStatus()->value,
            $member->isPrimary(),
            $member->isActive(),
        );
    }

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
            count($members),
            $primary->id()->value,
            $primary->name()->fullName(),
        );
    }

    private function profile(HouseholdMember $member): MemberProfileDto
    {
        $deactivation = $member->deactivation();

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
            $member->isPrimary(),
            $member->isActive(),
            $deactivation?->reason,
            $deactivation?->at->format(\DateTimeInterface::ATOM),
        );
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
