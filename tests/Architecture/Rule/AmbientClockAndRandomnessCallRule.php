<?php

declare(strict_types=1);

namespace App\Tests\Architecture\Rule;

use PhpParser\Node;
use PhpParser\Node\Expr\CallLike;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * CLAUDE.md: "Forbid `new \DateTimeImmutable()`, `time()`, `date()`,
 * `uniqid()`, `rand()`, `Uuid::v7()`, or static singletons inside Domain or
 * Application code — inject `Clock`, `IdentityGenerator`, or equivalent
 * ports instead." Unlike {@see AmbientDateTimeConstructionRule} these
 * functions have no legitimate "parse an existing value" call shape, so
 * every call from Domain or Application is reported.
 *
 * @implements Rule<CallLike>
 */
final class AmbientClockAndRandomnessCallRule implements Rule
{
    /** Ambient wall-clock reads. */
    private const CLOCK_FUNCTIONS = ['time', 'date', 'microtime', 'hrtime', 'date_create', 'date_create_immutable'];

    /** Ambient randomness sources. */
    private const RANDOMNESS_FUNCTIONS = ['uniqid', 'rand', 'mt_rand', 'random_int', 'random_bytes'];

    private const UID_NAMESPACE_PREFIX = 'Symfony\\Component\\Uid\\';

    public function __construct(private readonly LayerNamespaces $layers)
    {
    }

    public function getNodeType(): string
    {
        return CallLike::class;
    }

    /**
     * @param CallLike $node
     * @return list<IdentifierRuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if (!$this->layers->isDomainOrApplication($scope->getNamespace())) {
            return [];
        }

        if ($node instanceof FuncCall) {
            return $this->processFuncCall($node);
        }

        if ($node instanceof StaticCall) {
            return $this->processStaticCall($node, $scope);
        }

        return [];
    }

    /**
     * @return list<IdentifierRuleError>
     */
    private function processFuncCall(FuncCall $node): array
    {
        if (!$node->name instanceof Name) {
            return [];
        }

        $functionName = strtolower($node->name->getLast());

        if (in_array($functionName, self::CLOCK_FUNCTIONS, true)) {
            return [$this->buildError(
                sprintf('%s() reads the ambient system clock.', $functionName),
                'literec.ambientClock',
                'Inject Psr\\Clock\\ClockInterface and call ->now() instead.',
            )];
        }

        if (in_array($functionName, self::RANDOMNESS_FUNCTIONS, true)) {
            return [$this->buildError(
                sprintf('%s() reads an ambient randomness source.', $functionName),
                'literec.ambientRandomness',
                'Inject the context\'s IdentityGenerator port instead.',
            )];
        }

        return [];
    }

    /**
     * @return list<IdentifierRuleError>
     */
    private function processStaticCall(StaticCall $node, Scope $scope): array
    {
        if (!$node->class instanceof Name) {
            return [];
        }

        $resolvedClassName = $scope->resolveName($node->class);
        if (!str_starts_with($resolvedClassName, self::UID_NAMESPACE_PREFIX)) {
            return [];
        }

        return [$this->buildError(
            sprintf('%s generates an identifier from an ambient randomness source.', $resolvedClassName),
            'literec.ambientRandomness',
            'Inject the context\'s IdentityGenerator port instead.',
        )];
    }

    private function buildError(string $message, string $identifier, string $tip): IdentifierRuleError
    {
        return RuleErrorBuilder::message($message)
            ->identifier($identifier)
            ->tip($tip)
            ->build();
    }
}
