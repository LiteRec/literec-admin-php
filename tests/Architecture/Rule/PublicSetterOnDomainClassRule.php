<?php

declare(strict_types=1);

namespace App\Tests\Architecture\Rule;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassMethodNode;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * CLAUDE.md: "Forbid public setters on entities — all state changes go
 * through intention-revealing methods named after the domain action." No
 * off-the-shelf PHPat assertion covers method-name shape, so this walks
 * every method declared on a Domain class (entities, value objects,
 * interfaces, and enums alike — a port named `setX` is still a setter) and
 * flags a public `set*` method.
 *
 * @implements Rule<InClassMethodNode>
 */
final class PublicSetterOnDomainClassRule implements Rule
{
    private const SETTER_NAME = '/^set[A-Z]/';

    public function __construct(private readonly LayerNamespaces $layers)
    {
    }

    public function getNodeType(): string
    {
        return InClassMethodNode::class;
    }

    /**
     * @param InClassMethodNode $node
     * @return list<IdentifierRuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if (!$this->isPublicSetter($node, $scope)) {
            return [];
        }

        $methodReflection = $node->getMethodReflection();

        return [
            RuleErrorBuilder::message(sprintf(
                '%s::%s() is a public setter; Domain state changes must go through an '
                    . 'intention-revealing method named after the domain action.',
                $node->getClassReflection()->getName(),
                $methodReflection->getName(),
            ))
                ->identifier('literec.publicSetter')
                ->tip('Rename to the domain verb it performs, e.g. MemberInHousehold::changeResidency().')
                ->build(),
        ];
    }

    private function isPublicSetter(InClassMethodNode $node, Scope $scope): bool
    {
        if (!$this->layers->isDomain($scope->getNamespace())) {
            return false;
        }

        $methodReflection = $node->getMethodReflection();

        return $methodReflection->isPublic()
            && preg_match(self::SETTER_NAME, $methodReflection->getName()) === 1;
    }
}
