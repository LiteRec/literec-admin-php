<?php

declare(strict_types=1);

namespace App\Administration\Domain\ValueObject;

use App\Administration\Domain\Privilege;

/**
 * Immutable, de-duplicated collection of {@see Privilege} cases granted to
 * a Role aggregate. First occurrence wins on de-duplication.
 *
 * Modelled directly on {@see \App\Users\Domain\ValueObject\Roles}: a
 * mutation produces a new PrivilegeSet instance via with()/without(),
 * leaving the previous instance untouched so Doctrine's change detection
 * only marks the column dirty when the set of privileges actually
 * changed.
 */
final readonly class PrivilegeSet
{
    /** @var list<Privilege> */
    private array $items;

    private function __construct(Privilege ...$privileges)
    {
        $unique = [];
        foreach ($privileges as $privilege) {
            if (!in_array($privilege, $unique, true)) {
                $unique[] = $privilege;
            }
        }

        $this->items = $unique;
    }

    public static function of(Privilege ...$privileges): self
    {
        return new self(...$privileges);
    }

    public static function none(): self
    {
        return new self();
    }

    public function contains(Privilege $privilege): bool
    {
        return in_array($privilege, $this->items, true);
    }

    public function with(Privilege $privilege): self
    {
        if ($this->contains($privilege)) {
            return $this;
        }

        // A second spread appends the single new privilege: PHP rejects a
        // positional argument (`..., $privilege`) once a prior argument
        // has already been unpacked with `...`.
        return new self(...$this->items, ...[$privilege]);
    }

    public function without(Privilege $privilege): self
    {
        if (!$this->contains($privilege)) {
            return $this;
        }

        return new self(...array_values(array_filter(
            $this->items,
            static fn (Privilege $p): bool => $p !== $privilege,
        )));
    }

    public function count(): int
    {
        return count($this->items);
    }

    /**
     * @return list<Privilege>
     */
    public function toList(): array
    {
        return $this->items;
    }

    /**
     * @return list<string>
     */
    public function toNames(): array
    {
        return array_map(static fn (Privilege $privilege): string => $privilege->value, $this->items);
    }

    public function equals(self $other): bool
    {
        $ours = $this->toNames();
        $theirs = $other->toNames();
        sort($ours);
        sort($theirs);

        return $ours === $theirs;
    }
}
