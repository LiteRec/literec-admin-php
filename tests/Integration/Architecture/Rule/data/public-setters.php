<?php

declare(strict_types=1);

namespace App\Fixture\Domain;

final class DomainWithPublicSetter
{
    public function setStatus(): void
    {
        // Fixture: shape-only stub. The rule inspects visibility and the
        // method name, not behavior, so the body is intentionally empty.
    }
}

final class DomainWithPrivateSetter
{
    public function apply(string $status): void
    {
        $this->setStatus($status);
    }

    private function setStatus(string $status): void
    {
        // Fixture: shape-only stub, called from apply() above so it is not
        // itself dead code — a private setter must not be reported.
    }
}

final class DomainWithIntentionRevealingMethods
{
    public function settle(): void
    {
        // Fixture: shape-only stub; "settle" does not match the set[A-Z] shape.
    }

    public function changeResidency(): void
    {
        // Fixture: shape-only stub; an intention-revealing verb, not a setter.
    }
}

namespace App\Fixture\Application;

/**
 * Application is not gated by this rule, only Domain, so this is not
 * reported even though it matches the setter shape.
 */
final class ApplicationHandler
{
    public function setFoo(): void
    {
        // Fixture: shape-only stub; Application is out of scope for this rule.
    }
}
