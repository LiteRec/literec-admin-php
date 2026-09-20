<?php

declare(strict_types=1);

namespace App\Administration\Domain\ValueObject;

/**
 * Immutable, de-duplicated collection of {@see RoleId} granted to a Rank
 * aggregate. First occurrence wins on de-duplication.
 *
 * Modelled on {@see \App\Administration\Domain\ValueObject\PrivilegeSet},
 * with one difference: RoleId is a value object rather than a backed enum,
 * so two separately constructed instances holding the same UUID are never
 * `===` to each other. Containment and de-duplication therefore compare by
 * {@see RoleId::equals()} rather than strict identity.
 *
 * This set is in-memory only on {@see \App\Administration\Domain\Rank} —
 * it is persisted to the rank-roles join table by the Doctrine adapter,
 * not as a column on the rank row (see DoctrineRanks). The domain model is
 * unaffected by that persistence choice: a mutation still produces a new
 * AssignedRoles instance via with()/without(), leaving the previous
 * instance untouched.
 */
final readonly class AssignedRoles
{
    /** @var list<RoleId> */
    private array $items;

    private function __construct(RoleId ...$roleIds)
    {
        $unique = [];
        foreach ($roleIds as $roleId) {
            if (!self::containsId($unique, $roleId)) {
                $unique[] = $roleId;
            }
        }

        $this->items = $unique;
    }

    public static function of(RoleId ...$roleIds): self
    {
        return new self(...$roleIds);
    }

    public static function none(): self
    {
        return new self();
    }

    public function contains(RoleId $roleId): bool
    {
        return self::containsId($this->items, $roleId);
    }

    public function with(RoleId $roleId): self
    {
        if ($this->contains($roleId)) {
            return $this;
        }

        // A second spread appends the single new id: PHP rejects a
        // positional argument (`..., $roleId`) once a prior argument has
        // already been unpacked with `...`.
        return new self(...$this->items, ...[$roleId]);
    }

    public function without(RoleId $roleId): self
    {
        if (!$this->contains($roleId)) {
            return $this;
        }

        return new self(...array_values(array_filter(
            $this->items,
            static fn (RoleId $id): bool => !$id->equals($roleId),
        )));
    }

    public function count(): int
    {
        return count($this->items);
    }

    /**
     * @return list<RoleId>
     */
    public function toList(): array
    {
        return $this->items;
    }

    /**
     * @return list<string>
     */
    public function toStrings(): array
    {
        return array_map(static fn (RoleId $id): string => $id->value, $this->items);
    }

    public function equals(self $other): bool
    {
        $ours = $this->toStrings();
        $theirs = $other->toStrings();
        sort($ours);
        sort($theirs);

        return $ours === $theirs;
    }

    /**
     * @param list<RoleId> $haystack
     */
    private static function containsId(array $haystack, RoleId $needle): bool
    {
        foreach ($haystack as $id) {
            if ($id->equals($needle)) {
                return true;
            }
        }

        return false;
    }
}
