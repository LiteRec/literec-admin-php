<?php

declare(strict_types=1);

namespace App\Tests\Architecture\Rule;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * CLAUDE.md: "Forbid `new \DateTimeImmutable()` ... inside Domain or
 * Application code — inject `Clock` ... instead." `shouldNot()->construct()`
 * in PHPat is all-or-nothing per class, which would also flag the handlers
 * that legitimately parse an ISO-8601 string out of a command DTO (e.g.
 * `ReceivePurchaseOrderLineHandler` building a `DateTimeImmutable` from
 * `$command->receivedAtIso`). This rule inspects the constructor argument
 * instead: a `DateTime`/`DateTimeImmutable`/`Symfony\Component\Clock\DatePoint`
 * built with no `datetime` argument, or with one that `date_parse()` shows is
 * anchored to the current moment — a missing year/month/day component (as
 * `'now'`, `''`, `'today'`, or a bare time like `'10:00'` all are) or a
 * `relative` modifier (as `'+1 day'` is) — reads the ambient system clock;
 * any other `datetime` argument is parsing a value the caller already has,
 * which is the carve-out.
 *
 * @implements Rule<New_>
 */
final class AmbientDateTimeConstructionRule implements Rule
{
    private const AMBIENT_CLASSES = ['datetime', 'datetimeimmutable', 'symfony\\component\\clock\\datepoint'];
    private const DATETIME_PARAMETER = 'datetime';

    public function __construct(private readonly LayerNamespaces $layers)
    {
    }

    public function getNodeType(): string
    {
        return New_::class;
    }

    /**
     * @param New_ $node
     * @return list<IdentifierRuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        $resolvedClassName = $this->ambientClassName($node, $scope);
        if ($resolvedClassName === null) {
            return [];
        }

        return [
            RuleErrorBuilder::message(sprintf(
                '%s constructs the current moment from the ambient system clock.',
                $resolvedClassName,
            ))
                ->identifier('literec.ambientClock')
                ->tip('Inject Psr\\Clock\\ClockInterface and call ->now() instead.')
                ->build(),
        ];
    }

    /**
     * Resolves the constructed class name only when it is an ambient
     * DateTime(Immutable) built in Domain or Application; null otherwise.
     */
    private function ambientClassName(New_ $node, Scope $scope): ?string
    {
        if (!$node->class instanceof Name || !$this->layers->isDomainOrApplication($scope->getNamespace())) {
            return null;
        }

        $resolvedClassName = $scope->resolveName($node->class);
        if (!in_array(strtolower($resolvedClassName), self::AMBIENT_CLASSES, true)) {
            return null;
        }

        return $this->constructsAmbientNow($node, $scope) ? $resolvedClassName : null;
    }

    private function constructsAmbientNow(New_ $node, Scope $scope): bool
    {
        $datetimeArg = $this->datetimeArgument($node);
        if ($datetimeArg === null) {
            return true;
        }

        foreach ($scope->getType($datetimeArg->value)->getConstantStrings() as $constantString) {
            if ($this->isAnchoredToCurrentMoment($constantString->getValue())) {
                return true;
            }
        }

        return false;
    }

    /**
     * The positional first argument, or the one passed by name as
     * `datetime:`; null when neither is present (e.g. a `timezone:`-only
     * call, which still defaults its `datetime` parameter to `'now'`).
     */
    private function datetimeArgument(New_ $node): ?Arg
    {
        foreach ($node->getArgs() as $position => $arg) {
            if ($arg->name === null ? $position === 0 : $arg->name->toString() === self::DATETIME_PARAMETER) {
                return $arg;
            }
        }

        return null;
    }

    /**
     * A date/time format string is anchored to "now" when PHP leaves any of
     * its year/month/day components to the clock (as `'now'`, `''`,
     * `'today'`, or a bare time like `'10:00'` all do), or applies a
     * relative offset from the current moment (as `'+1 day'` does).
     */
    private function isAnchoredToCurrentMoment(string $format): bool
    {
        $parsed = date_parse($format);

        return $parsed['year'] === false
            || $parsed['month'] === false
            || $parsed['day'] === false
            || isset($parsed['relative']);
    }
}
