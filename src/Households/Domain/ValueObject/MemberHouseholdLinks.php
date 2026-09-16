<?php

declare(strict_types=1);

namespace App\Households\Domain\ValueObject;

use DateTimeImmutable;

/**
 * Read projection of a {@see \App\Households\Domain\HouseholdMember}'s
 * {@see \App\Households\Domain\HouseholdAffiliation} collection (LRA-237,
 * LRA-210): the other households a (necessarily minor) member has been
 * shared with, in addition to its home household.
 *
 * Modelled on {@see TransactionReferences}: an immutable, de-duplicated
 * (by household id, first occurrence wins) list of {@see HouseholdLink}.
 * Deliberately has no with()/without(): the write side is the
 * Doctrine-owned `$affiliations` collection on the entity
 * ({@see \App\Households\Domain\HouseholdMember::shareWith()},
 * {@see \App\Households\Domain\HouseholdMember::withdrawFrom()}), and this
 * type is a read-only projection of it, never itself persisted.
 */
final readonly class MemberHouseholdLinks
{
    /** @var list<HouseholdLink> */
    private array $items;

    private function __construct(HouseholdLink ...$links)
    {
        $unique = [];
        foreach ($links as $link) {
            if (!self::containsIn($unique, $link->householdId)) {
                $unique[] = $link;
            }
        }

        $this->items = $unique;
    }

    public static function of(HouseholdLink ...$links): self
    {
        return new self(...$links);
    }

    public static function none(): self
    {
        return new self();
    }

    public function count(): int
    {
        return count($this->items);
    }

    /**
     * @return list<HouseholdId>
     */
    public function householdIds(): array
    {
        return array_map(static fn(HouseholdLink $link): HouseholdId => $link->householdId, $this->items);
    }

    public function includes(HouseholdId $householdId): bool
    {
        return self::containsIn($this->items, $householdId);
    }

    public function linkedAt(HouseholdId $householdId): ?DateTimeImmutable
    {
        foreach ($this->items as $link) {
            if ($link->householdId->equals($householdId)) {
                return $link->linkedAt;
            }
        }

        return null;
    }

    public function equals(self $other): bool
    {
        if ($this->count() !== $other->count()) {
            return false;
        }

        foreach ($this->items as $link) {
            $theirLinkedAt = $other->linkedAt($link->householdId);
            if ($theirLinkedAt === null || $theirLinkedAt != $link->linkedAt) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<HouseholdLink> $haystack
     */
    private static function containsIn(array $haystack, HouseholdId $householdId): bool
    {
        foreach ($haystack as $link) {
            if ($link->householdId->equals($householdId)) {
                return true;
            }
        }

        return false;
    }
}
