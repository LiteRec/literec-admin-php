<?php

declare(strict_types=1);

namespace App\Tests\Architecture;

use PHPat\Selector\Selector;
use PHPat\Test\Attributes\TestRule;
use PHPat\Test\Builder\Rule;
use PHPat\Test\PHPat;

/**
 * Domain and Application code never constructs a generic SPL exception
 * (CLAUDE.md: "Forbid generic exception types (\RuntimeException,
 * \Exception) thrown from Domain code — define final domain exceptions with
 * named constructors"; the Anti-Patterns section extends the same rule to
 * Application).
 *
 * Extending an SPL type is still allowed (every App domain exception extends
 * \DomainException); only constructing one directly is forbidden. `new
 * self(...)` inside a subclass resolves to the subclass — PHPat's
 * `NewExtractor` resolves `self` through the enclosing class scope, and
 * `Selector::classname()` is an exact-name match, not an inheritance check —
 * so \DomainException can be listed here without false-positiving on any
 * existing domain exception's named constructor.
 *
 * The `excluding(App\Tests)` below is currently unreachable: the single
 * `\w+` segment in {@see self::DOMAIN_OR_APPLICATION_NAMESPACE} already
 * stops `App\Tests\Unit\...\Domain\...` from matching the primary selector
 * (it has two segments, "Tests" and "Unit", before "Domain"). It is kept
 * anyway for consistency with every LRA-198 subject selector and as a
 * guard against a future rename of the test namespace layout, not because
 * tests are in scope today.
 */
final class NoSplExceptionsInDomainOrApplicationRule
{
    private const DOMAIN_OR_APPLICATION_NAMESPACE = '/^App\\\\\w+\\\\(Domain|Application)\b/';

    #[TestRule]
    public function domain_and_application_do_not_construct_spl_exceptions(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace(self::DOMAIN_OR_APPLICATION_NAMESPACE, true))
            ->excluding(Selector::inNamespace('App\Tests'))
            ->shouldNot()
            ->construct()
            ->classes(
                Selector::classname(\Exception::class),
                Selector::classname(\DomainException::class),
                Selector::classname(\LogicException::class),
                Selector::classname(\RuntimeException::class),
                Selector::classname(\InvalidArgumentException::class),
                Selector::classname(\BadFunctionCallException::class),
                Selector::classname(\BadMethodCallException::class),
                Selector::classname(\LengthException::class),
                Selector::classname(\OutOfRangeException::class),
                Selector::classname(\OutOfBoundsException::class),
                Selector::classname(\OverflowException::class),
                Selector::classname(\RangeException::class),
                Selector::classname(\UnderflowException::class),
                Selector::classname(\UnexpectedValueException::class),
            )
            ->because('CLAUDE.md: Domain and Application failures are named final exceptions with named constructors.');
    }
}
