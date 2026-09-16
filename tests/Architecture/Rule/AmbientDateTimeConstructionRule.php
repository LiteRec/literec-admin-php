<?php

declare(strict_types=1);

namespace App\Tests\Architecture\Rule;

use PhpParser\Node;
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
 * instead: a `DateTime`/`DateTimeImmutable` built with no argument, or with
 * a `'now'` argument (literal, named, or a resolvable class constant), reads
 * the ambient system clock; any other first argument is parsing a value the
 * caller already has, which is the carve-out.
 *
 * @implements Rule<New_>
 */
final class AmbientDateTimeConstructionRule implements Rule
{
    private const AMBIENT_CLASSES = ['datetime', 'datetimeimmutable'];
    private const NOW = 'now';

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
        if (!$node->class instanceof Name) {
            return [];
        }

        if (!$this->layers->isDomainOrApplication($scope->getNamespace())) {
            return [];
        }

        $resolvedClassName = $scope->resolveName($node->class);
        if (!in_array(strtolower($resolvedClassName), self::AMBIENT_CLASSES, true)) {
            return [];
        }

        if (!$this->constructsAmbientNow($node, $scope)) {
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

    private function constructsAmbientNow(New_ $node, Scope $scope): bool
    {
        $args = $node->getArgs();
        if ($args === []) {
            return true;
        }

        foreach ($scope->getType($args[0]->value)->getConstantStrings() as $constantString) {
            if (strtolower($constantString->getValue()) === self::NOW) {
                return true;
            }
        }

        return false;
    }
}
