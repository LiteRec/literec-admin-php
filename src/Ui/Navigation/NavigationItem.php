<?php

declare(strict_types=1);

namespace App\Ui\Navigation;

/**
 * A single entry in the main navigation: a top-level category or a
 * sub-item underneath one. Children are themselves NavigationItem
 * instances so the structure is uniform top to bottom.
 */
final readonly class NavigationItem
{
    /**
     * @param list<NavigationItem> $children
     */
    public function __construct(
        public string $label,
        public string $route,
        public array $children = [],
    ) {
    }

    public function hasChildren(): bool
    {
        return $this->children !== [];
    }
}
