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
 * \DomainException is deliberately excluded from the target list: every App
 * domain exception extends it, and PHPat's construct assertion is only
 * documented for `new` expressions, not for the implicit
 * `parent::__construct()` a named constructor makes when building `new
 * self(...)`. Including it would false-positive on every domain exception
 * this rule is meant to protect.
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
