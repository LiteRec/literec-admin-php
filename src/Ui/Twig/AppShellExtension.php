<?php

declare(strict_types=1);

namespace App\Ui\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * Exposes Twig helpers consumed by the authenticated app shell
 * (app.html.twig): the selected-facility indicator shown in the header, the
 * build version string shown in the footer, and the avatar-initials filter for
 * the signed-in user. The facility and build values are pulled from container
 * parameters so they can be tuned per environment without code changes.
 */
final class AppShellExtension extends AbstractExtension
{
    public function __construct(
        private readonly string $selectedFacility,
        private readonly string $buildVersion,
    ) {
    }

    /**
     * @return list<TwigFunction>
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('selected_facility', $this->selectedFacility(...)),
            new TwigFunction('build_version', $this->buildVersion(...)),
        ];
    }

    /**
     * @return list<TwigFilter>
     */
    public function getFilters(): array
    {
        return [
            new TwigFilter('user_initials', $this->userInitials(...)),
        ];
    }

    public function selectedFacility(): string
    {
        return $this->selectedFacility;
    }

    public function buildVersion(): string
    {
        return $this->buildVersion;
    }

    /**
     * Derives up to two uppercase initials from a display name or username for
     * the header avatar. Words are split on whitespace and the common username
     * separators (".", "_", "-"); "Leslie Knope" and "leslie.knope" both yield
     * "LK", while a single token like "lknope" yields "L". Returns an empty
     * string only for an empty/blank input.
     */
    public function userInitials(string $name): string
    {
        $words = preg_split('/[\s._-]+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $initials = '';
        foreach (array_slice($words, 0, 2) as $word) {
            $initials .= mb_strtoupper(mb_substr($word, 0, 1));
        }

        return $initials;
    }
}
