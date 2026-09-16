<?php

declare(strict_types=1);

namespace App\Tests\Architecture\Rule;

/**
 * Shared namespace classification for the custom architecture rules below.
 *
 * Keeping the "which layer is this namespace in" check in one collaborator
 * (rather than duplicating a regex in every rule) means the bounded-context
 * segment pattern (`App\<Context>\...`) has exactly one place to change.
 */
final class LayerNamespaces
{
    private const DOMAIN_OR_APPLICATION = '/^App\\\\[A-Za-z]+\\\\(Domain|Application)(\\\\|$)/';
    private const DOMAIN = '/^App\\\\[A-Za-z]+\\\\Domain(\\\\|$)/';

    public function isDomainOrApplication(?string $namespace): bool
    {
        return $namespace !== null && preg_match(self::DOMAIN_OR_APPLICATION, $namespace) === 1;
    }

    public function isDomain(?string $namespace): bool
    {
        return $namespace !== null && preg_match(self::DOMAIN, $namespace) === 1;
    }
}
