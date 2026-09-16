<?php

declare(strict_types=1);

namespace App\Fixture\Domain;

final class DomainWithPublicSetter
{
    public function setStatus(string $status): void
    {
    }
}

final class DomainWithPrivateSetter
{
    private function setStatus(string $status): void
    {
    }
}

final class DomainWithIntentionRevealingMethods
{
    public function settle(): void
    {
    }

    public function changeResidency(): void
    {
    }
}

namespace App\Fixture\Application;

/**
 * Application is not gated by this rule, only Domain, so this is not
 * reported even though it matches the setter shape.
 */
final class ApplicationHandler
{
    public function setFoo(string $foo): void
    {
    }
}
