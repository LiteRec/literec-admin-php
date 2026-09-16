<?php

declare(strict_types=1);

namespace App\Tests\Integration\Architecture\Rule;

use App\Tests\Architecture\Rule\LayerNamespaces;
use App\Tests\Architecture\Rule\PublicSetterOnDomainClassRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * @extends RuleTestCase<PublicSetterOnDomainClassRule>
 */
#[Medium]
#[Group('architecture')]
final class PublicSetterOnDomainClassRuleTest extends RuleTestCase
{
    #[Test]
    #[TestDox(
        'Reports a public set*() method on a Domain class, not a private setter, a non-setter-shaped method, '
            . 'or the same shape on an Application class.',
    )]
    public function it_reports_public_setters_on_domain_classes_only(): void
    {
        $this->analyse([__DIR__ . '/data/public-setters.php'], [
            [
                'App\\Fixture\\Domain\\DomainWithPublicSetter::setStatus() is a public setter; Domain state '
                    . 'changes must go through an intention-revealing method named after the domain action.',
                9,
                'Rename to the domain verb it performs, e.g. Household::changeMemberResidency().',
            ],
        ]);
    }

    protected function getRule(): Rule
    {
        return new PublicSetterOnDomainClassRule(new LayerNamespaces());
    }
}
