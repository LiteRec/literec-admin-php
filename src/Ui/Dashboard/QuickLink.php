<?php

declare(strict_types=1);

namespace App\Ui\Dashboard;

/**
 * A single Quick Actions tile on the dashboard. Route is a Symfony route name;
 * the template resolves it through path() so the tile follows any URL changes
 * to the underlying section. `icon` is one of the curated icon names (see
 * components/_icon.html.twig).
 */
final readonly class QuickLink
{
    public function __construct(
        public string $label,
        public string $route,
        public string $icon,
    ) {
    }
}
