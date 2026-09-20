<?php

declare(strict_types=1);

namespace App\Administration\Application\Query;

use App\Administration\Application\Query\View\PrivilegeCatalogueView;
use App\Administration\Application\Query\View\PrivilegeGroupView;
use App\Administration\Application\Query\View\PrivilegeView;
use App\Administration\Domain\Privilege;
use App\Administration\Domain\PrivilegeCatalogue;
use App\Administration\Domain\PrivilegeGroup;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final class ListPrivilegeCatalogueHandler
{
    public function __construct(
        private readonly PrivilegeCatalogue $catalogue,
    ) {
    }

    public function __invoke(ListPrivilegeCatalogue $query): PrivilegeCatalogueView
    {
        $groupedPrivileges = [];
        foreach ($this->catalogue->orderedForScreen() as $privilege) {
            $groupedPrivileges[$privilege->definition()->group->value][] = $privilege;
        }

        $groupViews = [];
        foreach ($groupedPrivileges as $privileges) {
            $group = $privileges[0]->definition()->group;
            $groupViews[] = $this->toGroupView($group, $privileges);
        }

        return new PrivilegeCatalogueView($groupViews);
    }

    /**
     * @param list<Privilege> $privileges
     */
    private function toGroupView(PrivilegeGroup $group, array $privileges): PrivilegeGroupView
    {
        return new PrivilegeGroupView(
            $group->value,
            $group->displayName(),
            array_map(self::toPrivilegeView(...), $privileges),
        );
    }

    private static function toPrivilegeView(Privilege $privilege): PrivilegeView
    {
        $definition = $privilege->definition();

        return new PrivilegeView(
            $privilege->value,
            $definition->displayName,
            $definition->description,
            $definition->order,
        );
    }
}
