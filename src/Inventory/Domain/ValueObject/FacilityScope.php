<?php

declare(strict_types=1);

namespace App\Inventory\Domain\ValueObject;

use App\Inventory\Domain\Exception\FacilityScopeEmpty;

/**
 * Either the singleton "all facilities" marker or a non-empty,
 * deduplicated, sorted list of specific facility codes.
 *
 * Modeled as enum-or-list (not a bare enum) because the bounded set
 * of facilities is data, not code.
 */
final readonly class FacilityScope
{
    /** @var list<FacilityCode> */
    public array $facilities;
    public bool $isAll;

    /**
     * @param list<FacilityCode> $facilities Empty when isAll=true.
     */
    private function __construct(bool $isAll, array $facilities)
    {
        $this->isAll = $isAll;
        $this->facilities = $facilities;
    }

    public static function all(): self
    {
        return new self(true, []);
    }

    /**
     * @param list<FacilityCode> $facilities
     */
    public static function ofFacilities(array $facilities): self
    {
        if ($facilities === []) {
            throw FacilityScopeEmpty::create();
        }

        // Dedup + sort by canonical value so equality is byte-stable.
        $byValue = [];
        foreach ($facilities as $facility) {
            $byValue[$facility->value] = $facility;
        }
        ksort($byValue);

        return new self(false, array_values($byValue));
    }

    public function isAll(): bool
    {
        return $this->isAll;
    }

    public function includes(FacilityCode $facility): bool
    {
        if ($this->isAll) {
            return true;
        }

        foreach ($this->facilities as $f) {
            if ($f->equals($facility)) {
                return true;
            }
        }

        return false;
    }

    public function equals(self $other): bool
    {
        if (
            $this->isAll !== $other->isAll
            || count($this->facilities) !== count($other->facilities)
        ) {
            return false;
        }

        foreach ($this->facilities as $i => $facility) {
            if (! $facility->equals($other->facilities[$i])) {
                return false;
            }
        }

        return true;
    }
}
