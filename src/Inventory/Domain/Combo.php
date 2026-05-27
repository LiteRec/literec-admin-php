<?php

declare(strict_types=1);

namespace App\Inventory\Domain;

use App\Catalog\Domain\ValueObject\ListingId;
use App\Inventory\Domain\Event\ComboArchived;
use App\Inventory\Domain\Event\ComboComponentsUpdated;
use App\Inventory\Domain\Event\ComboDefined;
use App\Inventory\Domain\Exception\ComboCycleDetected;
use App\Inventory\Domain\Exception\ComboDepthExceeded;
use App\Inventory\Domain\Exception\ComboIsArchived;
use App\Inventory\Domain\Exception\ComboMayNotContainCombo;
use App\Inventory\Domain\Exception\ComboRequiresComponents;
use App\Inventory\Domain\Exception\InvalidComboComponent;
use App\Inventory\Domain\ValueObject\ComboComponent;
use App\Inventory\Domain\ValueObject\ComboId;
use App\Inventory\Domain\ValueObject\InventoryItemId;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Psr\Clock\ClockInterface;

/**
 * Combo aggregate: a sellable Catalog Listing that, at sale time,
 * decrements multiple component Inventory items' on-hand stocks by
 * configured ratios.
 *
 * The aggregate enforces two cross-cutting invariants at define-time
 * and on any subsequent component update:
 *  - **No nesting:** every component must reference an atomic
 *    InventoryItem, never another combo (enforced via the injected
 *    {@see ComboGraphResolver::isCombo()} probe).
 *  - **No cycles:** the graph reachable from the components must not
 *    revisit any of them. The check is defensive — with no-nesting
 *    enforced, the resolver returns no children in production — but
 *    the BFS exists so a future relaxation of the no-nesting rule
 *    does not silently introduce cycle-prone bundles.
 *
 * Sale-time expansion lives in the Inventory ACL handler (LRA-83);
 * this aggregate is the source of the component graph it walks.
 */
final class Combo
{
    use AggregateRoot;

    /**
     * Defensive depth cap. With the no-nesting rule active the BFS
     * terminates at depth 1, but the limit prevents pathological
     * walks if the resolver ever returns stale or corrupt graph data.
     */
    private const int MAX_DEPTH = 8;

    private ComboId $id;
    private ListingId $listingId;
    /** @var Collection<int, ComboComponentEntity> */
    private Collection $components;
    private bool $archived;
    private DateTimeImmutable $definedAt;
    private DateTimeImmutable $updatedAt;

    private function __construct()
    {
        // Doctrine instantiates the aggregate through reflection without
        // invoking define(); initialise the components collection here
        // so the empty case (a hydrated combo with no rows yet) does
        // not leave an undefined property and so the named constructor
        // can immediately mutate the collection rather than re-create it.
        $this->components = new ArrayCollection();
    }

    /**
     * @param list<ComboComponent> $components
     */
    public static function define(
        ComboId $id,
        ListingId $listingId,
        array $components,
        ComboGraphResolver $resolver,
        ClockInterface $clock,
    ): self {
        if ($components === []) {
            throw ComboRequiresComponents::empty();
        }

        $combo = new self();
        $combo->id = $id;
        $combo->listingId = $listingId;
        $combo->archived = false;
        $combo->definedAt = $clock->now();
        $combo->updatedAt = $combo->definedAt;

        $validated = self::validatedComponents($id, $components, $resolver);
        foreach ($validated as $vo) {
            $combo->components->add(new ComboComponentEntity(
                $combo,
                $vo->componentItemId,
                $vo->quantityPerCombo,
            ));
        }

        $combo->recordThat(new ComboDefined($id, $listingId, $validated, $combo->definedAt));

        return $combo;
    }

    public function id(): ComboId
    {
        return $this->id;
    }

    public function listingId(): ListingId
    {
        return $this->listingId;
    }

    /**
     * @return list<ComboComponent>
     */
    public function components(): array
    {
        return array_values(array_map(
            static fn (ComboComponentEntity $entity): ComboComponent => $entity->toValueObject(),
            $this->components->toArray(),
        ));
    }

    public function isArchived(): bool
    {
        return $this->archived;
    }

    public function definedAt(): DateTimeImmutable
    {
        return $this->definedAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * @param list<ComboComponent> $components
     */
    public function replaceComponents(
        array $components,
        ComboGraphResolver $resolver,
        ClockInterface $clock,
    ): void {
        $this->guardNotArchived();

        if ($components === []) {
            throw ComboRequiresComponents::empty();
        }

        $next = self::validatedComponents($this->id, $components, $resolver);

        if (self::componentsEqual($this->components(), $next)) {
            return;
        }

        // Diff the existing entities against the next set so Doctrine's
        // UnitOfWork never holds two entities claiming the same composite
        // identity (combo_id, item_id). Reuse existing rows for kept
        // items (in-place qty update), schedule orphan removal for
        // dropped items, and add fresh rows only for genuinely new items.
        // The final clear + add cycle reorders the collection to the
        // canonical $next order so iteration matches the event payload.
        /** @var array<string, ComboComponentEntity> $existingByItem */
        $existingByItem = [];
        foreach ($this->components as $entity) {
            $existingByItem[$entity->componentItemId()->value] = $entity;
        }

        /** @var list<ComboComponentEntity> $ordered */
        $ordered = [];
        foreach ($next as $vo) {
            $itemKey = $vo->componentItemId->value;
            if (isset($existingByItem[$itemKey])) {
                $existingByItem[$itemKey]->changeQuantityTo($vo->quantityPerCombo);
                $ordered[] = $existingByItem[$itemKey];
                unset($existingByItem[$itemKey]);
            } else {
                $ordered[] = new ComboComponentEntity(
                    $this,
                    $vo->componentItemId,
                    $vo->quantityPerCombo,
                );
            }
        }

        foreach ($existingByItem as $entity) {
            $this->components->removeElement($entity);
        }

        $this->components->clear();
        foreach ($ordered as $entity) {
            $this->components->add($entity);
        }

        $this->updatedAt = $clock->now();
        $this->recordThat(new ComboComponentsUpdated($this->id, $next, $this->updatedAt));
    }

    public function archive(ClockInterface $clock): void
    {
        if ($this->archived) {
            return;
        }

        $this->archived = true;
        $this->updatedAt = $clock->now();
        $this->recordThat(new ComboArchived($this->id, $this->updatedAt));
    }

    /**
     * @param list<ComboComponent> $components
     * @return list<ComboComponent>
     */
    private static function validatedComponents(
        ComboId $comboId,
        array $components,
        ComboGraphResolver $resolver,
    ): array {
        /** @var array<string, true> $seen */
        $seen = [];
        foreach ($components as $component) {
            $itemKey = $component->componentItemId->value;
            if (isset($seen[$itemKey])) {
                throw InvalidComboComponent::duplicateComponent($component->componentItemId);
            }
            $seen[$itemKey] = true;

            if ($resolver->isCombo($component->componentItemId)) {
                throw ComboMayNotContainCombo::withComponent($component->componentItemId);
            }
        }

        self::detectCycles($comboId, $components, $resolver);

        return $components;
    }

    /**
     * @param list<ComboComponent> $components
     */
    private static function detectCycles(
        ComboId $comboId,
        array $components,
        ComboGraphResolver $resolver,
    ): void {
        /** @var array<string, true> $visited */
        $visited = [];
        foreach ($components as $component) {
            $visited[$component->componentItemId->value] = true;
        }

        /** @var list<InventoryItemId> $frontier */
        $frontier = array_map(
            static fn (ComboComponent $c): InventoryItemId => $c->componentItemId,
            $components,
        );

        $depth = 0;
        while ($frontier !== []) {
            ++$depth;
            if ($depth > self::MAX_DEPTH) {
                throw ComboDepthExceeded::atDepth($depth, self::MAX_DEPTH);
            }

            $next = [];
            foreach ($frontier as $itemId) {
                foreach ($resolver->componentsOf($itemId) as $childId) {
                    if (isset($visited[$childId->value])) {
                        throw ComboCycleDetected::at($comboId, $childId);
                    }
                    $visited[$childId->value] = true;
                    $next[] = $childId;
                }
            }
            $frontier = $next;
        }
    }

    /**
     * @param list<ComboComponent> $a
     * @param list<ComboComponent> $b
     */
    private static function componentsEqual(array $a, array $b): bool
    {
        if (count($a) !== count($b)) {
            return false;
        }

        foreach ($a as $i => $component) {
            if (! $component->equals($b[$i])) {
                return false;
            }
        }

        return true;
    }

    private function guardNotArchived(): void
    {
        if ($this->archived) {
            throw ComboIsArchived::for($this->id);
        }
    }
}
