<?php

declare(strict_types=1);

namespace App\Administration\Domain;

/**
 * Projects {@see Privilege::cases()} into the stable group-then-order
 * sequence the permission screen renders. Stateless: every fact needed
 * to build the sequence lives on the enum's own
 * {@see Privilege::definition()}, so this class carries no constructor
 * arguments and no state of its own.
 */
final class PrivilegeCatalogue
{
    /**
     * @return list<Privilege> every case, ordered by its group's
     *                         {@see PrivilegeGroup::sortOrder()} then by
     *                         its own {@see PrivilegeDefinition::$order}
     *                         within that group
     */
    public function orderedForScreen(): array
    {
        $cases = Privilege::cases();

        usort($cases, static function (Privilege $a, Privilege $b): int {
            $definitionA = $a->definition();
            $definitionB = $b->definition();

            return [$definitionA->group->sortOrder(), $definitionA->order]
                <=> [$definitionB->group->sortOrder(), $definitionB->order];
        });

        return $cases;
    }
}
