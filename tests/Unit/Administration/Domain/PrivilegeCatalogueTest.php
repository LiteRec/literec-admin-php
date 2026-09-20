<?php

declare(strict_types=1);

namespace App\Tests\Unit\Administration\Domain;

use App\Administration\Domain\Privilege;
use App\Administration\Domain\PrivilegeCatalogue;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[Small]
final class PrivilegeCatalogueTest extends TestCase
{
    #[Test]
    #[TestDox('Grouping is complete: every case appears exactly once in the ordered sequence.')]
    public function grouping_is_complete(): void
    {
        $ordered = (new PrivilegeCatalogue())->orderedForScreen();
        $toValues = static fn (Privilege $privilege): string => $privilege->value;

        self::assertCount(count(Privilege::cases()), $ordered);
        self::assertSame(
            array_map($toValues, $this->sortedByValue(Privilege::cases())),
            array_map($toValues, $this->sortedByValue($ordered)),
        );
    }

    #[Test]
    #[TestDox('Ordering is deterministic across repeated calls.')]
    public function ordering_is_deterministic(): void
    {
        $catalogue = new PrivilegeCatalogue();
        $toValues = static fn (Privilege $privilege): string => $privilege->value;

        $first = array_map($toValues, $catalogue->orderedForScreen());
        $second = array_map($toValues, $catalogue->orderedForScreen());

        self::assertSame($first, $second);
    }

    #[Test]
    #[TestDox('Orders every case by its group\'s sort order, then by its own order within that group.')]
    public function orders_by_group_then_order(): void
    {
        $ordered = (new PrivilegeCatalogue())->orderedForScreen();

        $sortKeys = array_map(
            static function (Privilege $privilege): array {
                $definition = $privilege->definition();

                return [$definition->group->sortOrder(), $definition->order];
            },
            $ordered,
        );

        $expected = $sortKeys;
        usort($expected, static fn (array $a, array $b): int => $a <=> $b);

        self::assertSame($expected, $sortKeys);
    }

    /**
     * @param list<Privilege> $privileges
     *
     * @return list<Privilege>
     */
    private function sortedByValue(array $privileges): array
    {
        usort($privileges, static fn (Privilege $a, Privilege $b): int => $a->value <=> $b->value);

        return $privileges;
    }
}
