<?php

declare(strict_types=1);

namespace App\Tests\Architecture;

use PHPat\Selector\Selector;
use PHPat\Test\Attributes\TestRule;
use PHPat\Test\Builder\Rule;
use PHPat\Test\PHPat;

/**
 * No App class extends another concrete App class (CLAUDE.md: "Forbid
 * `extends` between concrete classes — a `final` class extending another
 * `final` (or non-`final`) concrete class is always a smell; replace with a
 * collaborator field.").
 *
 * SPL/framework parents (DomainException, RuntimeException, Doctrine\Type,
 * Symfony's AbstractController/AbstractType/Command, ...) are explicitly
 * permitted by CLAUDE.md and are not App-owned, so they are left alone here.
 * The target also excludes interfaces: PHPat's "extend" assertion inspects
 * an interface's extended interfaces the same way it inspects a class's
 * parent class, and interfaces are never reported as abstract by
 * reflection, so without this exclusion App's exception-marker interfaces
 * (e.g. SharedDomainException extended by InventoryDomainException) would
 * be flagged even though interface extension is not the concrete-class
 * inheritance this rule targets.
 */
final class NoConcreteInheritanceRule
{
    #[TestRule]
    public function classes_do_not_extend_a_concrete_app_class(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace('App'))
            ->excluding(Selector::inNamespace('App\Tests'))
            ->shouldNot()
            ->extend()
            ->classes(Selector::AllOf(
                Selector::inNamespace('App'),
                Selector::Not(Selector::isAbstract()),
                Selector::Not(Selector::isInterface()),
            ))
            ->because('CLAUDE.md: forbid extends between concrete classes; compose instead.');
    }
}
