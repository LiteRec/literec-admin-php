<?php

declare(strict_types=1);

namespace App\Ui\Dashboard;

/**
 * A single Quick Links tile on the dashboard. Route is a Symfony route
 * name; the template resolves it through path() so the tile follows any
 * URL changes to the underlying section.
 */
final readonly class QuickLink
{
    public function __construct(
        public string $label,
        public string $route,
    ) {
    }
}
