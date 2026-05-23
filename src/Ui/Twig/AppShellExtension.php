<?php

declare(strict_types=1);

namespace App\Ui\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Exposes Twig functions consumed by the authenticated app shell
 * (app.html.twig): the selected-facility indicator shown in the header and
 * the build version string shown in the footer. Both values are pulled
 * from container parameters so they can be tuned per environment without
 * code changes.
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

    public function selectedFacility(): string
    {
        return $this->selectedFacility;
    }

    public function buildVersion(): string
    {
        return $this->buildVersion;
    }
}
