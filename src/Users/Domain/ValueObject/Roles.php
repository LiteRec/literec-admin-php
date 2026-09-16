<?php

declare(strict_types=1);

namespace App\Users\Domain\ValueObject;

/**
 * Immutable, de-duplicated collection of {@see Role} values granted to a
 * User aggregate (LRA-236). First occurrence wins on de-duplication.
 *
 * Absorbs the mutation logic User::grantRole()/revokeRole() used to inline:
 * a change produces a new Roles instance via with()/without(), leaving the
 * previous instance untouched so Doctrine's change detection only marks
 * the column dirty when the set of roles actually changed.
 */
final readonly class Roles
{
    /** @var list<Role> */
    private array $items;

    private function __construct(Role ...$roles)
    {
        $unique = [];
        foreach ($roles as $role) {
            if (!in_array($role, $unique, true)) {
                $unique[] = $role;
            }
        }

        $this->items = $unique;
    }

    public static function of(Role ...$roles): self
    {
        return new self(...$roles);
    }

    public static function none(): self
    {
        return new self();
    }

    public function contains(Role $role): bool
    {
        return in_array($role, $this->items, true);
    }

    public function with(Role $role): self
    {
        if ($this->contains($role)) {
            return $this;
        }

        // A second spread appends the single new role: PHP rejects a
        // positional argument (`..., $role`) once a prior argument has
        // already been unpacked with `...`.
        return new self(...$this->items, ...[$role]);
    }

    public function without(Role $role): self
    {
        if (!$this->contains($role)) {
            return $this;
        }

        return new self(...array_values(array_filter(
            $this->items,
            static fn(Role $r): bool => $r !== $role,
        )));
    }

    public function count(): int
    {
        return count($this->items);
    }

    /**
     * @return list<Role>
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
        return array_map(static fn(Role $role): string => $role->value, $this->items);
    }

    public function equals(self $other): bool
    {
        $ours = $this->toStrings();
        $theirs = $other->toStrings();
        sort($ours);
        sort($theirs);

        return $ours === $theirs;
    }
}
