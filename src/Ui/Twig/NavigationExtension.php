<?php

declare(strict_types=1);

namespace App\Ui\Twig;

use App\Ui\Navigation\MainNavigation;
use App\Ui\Navigation\NavigationItem;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Twig surface for the staff-admin main navigation. Exposes the
 * canonical structure as a function so the nav component never
 * hardcodes its own copy, plus a small predicate the component uses to
 * highlight the current section.
 */
final class NavigationExtension extends AbstractExtension
{
    /**
     * @return list<TwigFunction>
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('main_navigation', $this->mainNavigation(...)),
            new TwigFunction('is_active_nav_item', $this->isActiveNavItem(...)),
        ];
    }

    public function mainNavigation(): MainNavigation
    {
        return MainNavigation::build();
    }

    public function isActiveNavItem(NavigationItem $item, ?string $currentRoute): bool
    {
        if ($currentRoute === null || $currentRoute === '') {
            return false;
        }

        foreach ($item->children as $child) {
            if ($child->route === $currentRoute) {
                return true;
            }
        }

        // A top-level item is also active when its own route matches.
        return $item->route === $currentRoute;
    }
}
