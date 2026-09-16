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
    private const CLOCK_FUNCTIONS = [
        'time', 'date', 'gmdate', 'idate', 'getdate', 'localtime',
        'microtime', 'hrtime', 'mktime', 'gmmktime', 'strtotime',
        'date_create', 'date_create_immutable',
        // symfony/clock's global helper, called as `now()` via
        // `use function Symfony\Component\Clock\now;`; matched on the
        // unqualified name like every other entry here (see
        // processFuncCall()), so a same-named user function in Domain or
        // Application would also be reported — an accepted false positive
        // for a syntactic rule, given how narrow that collision is.
        'now',
    ];

    /** Ambient randomness sources. */
    private const RANDOMNESS_FUNCTIONS = [
        'uniqid', 'rand', 'mt_rand', 'random_int', 'random_bytes',
        'lcg_value', 'shuffle', 'str_shuffle', 'array_rand',
    ];

    private const UID_NAMESPACE_PREFIX = 'Symfony\\Component\\Uid\\';
    private const CLOCK_SINGLETON_CLASS = 'symfony\\component\\clock\\clock';
    private const CLOCK_TIP = 'Inject Psr\\Clock\\ClockInterface and call ->now() instead.';
    private const RANDOMNESS_TIP = 'Inject the context\'s IdentityGenerator port instead.';

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

        return match (true) {
            $node instanceof FuncCall => $this->processFuncCall($node),
            $node instanceof StaticCall => $this->processStaticCall($node, $scope),
            default => [],
        };
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

        return match (true) {
            in_array($functionName, self::CLOCK_FUNCTIONS, true) => [$this->buildError(
                sprintf('%s() reads the ambient system clock.', $functionName),
                'literec.ambientClock',
                self::CLOCK_TIP,
            )],
            in_array($functionName, self::RANDOMNESS_FUNCTIONS, true) => [$this->buildError(
                sprintf('%s() reads an ambient randomness source.', $functionName),
                'literec.ambientRandomness',
                self::RANDOMNESS_TIP,
            )],
            default => [],
        };
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

        return match (true) {
            strtolower($resolvedClassName) === self::CLOCK_SINGLETON_CLASS => [$this->buildError(
                sprintf('%s is a static clock singleton.', $resolvedClassName),
                'literec.ambientClock',
                self::CLOCK_TIP,
            )],
            str_starts_with($resolvedClassName, self::UID_NAMESPACE_PREFIX) => [$this->buildError(
                sprintf('%s generates an identifier from an ambient randomness source.', $resolvedClassName),
                'literec.ambientRandomness',
                self::RANDOMNESS_TIP,
            )],
            default => [],
        };
    }

    private function buildError(string $message, string $identifier, string $tip): IdentifierRuleError
    {
        return RuleErrorBuilder::message($message)
            ->identifier($identifier)
            ->tip($tip)
            ->build();
    }
}
