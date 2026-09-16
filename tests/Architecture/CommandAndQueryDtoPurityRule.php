<?php

declare(strict_types=1);

namespace App\Tests\Architecture;

use PHPat\Selector\Selector;
use PHPat\Selector\SelectorInterface;
use PHPat\Test\Attributes\TestRule;
use PHPat\Test\Builder\Rule;
use PHPat\Test\PHPat;

/**
 * Command and query DTOs are pure data: no Domain dependency, no framework
 * dependency, no mutation (CLAUDE.md: "Forbid framework types inside
 * command/query DTOs ... DTOs carry primitives ... so they are trivially
 * serializable"; the Immutability section: "Every command DTO, query DTO
 * ... must be final with readonly constructor-promoted properties").
 *
 * The subject selector picks the 91 DTOs under a context's
 * `Application\Command` or `Application\Query` namespace (including the
 * `Query\View`, `Query\Port`, and `Query\Report` sub-namespaces —
 * `[A-Za-z]+` cannot cross the trailing `(\\|$)` boundary, so it still
 * anchors on exactly one context segment after `App`) while excluding:
 * - `*Handler` classes, which orchestrate and are allowed to depend on
 *   Domain;
 * - interfaces, enums, and traits, none of which `beReadonly()` can assess
 *   (`ClassReflection::isReadOnly()` is false for all three) and one of
 *   which — `ReleasesHouseholdEvents` — legitimately imports Domain events.
 *
 * That leaves `MemberReadModel` and `InventoryReadModel` (Application query
 * port interfaces) and `MembersSegment` (an enum) excluded by the interface/
 * enum/trait filters rather than by name, and `ReleasesHouseholdEvents`
 * excluded as the only trait in scope. All four are also the only types in
 * these namespaces that import Domain, which is what keeps this rule at a
 * zero baseline without a single named exclusion.
 *
 * Form-bound input classes (the 25 `*Input` classes backing Symfony forms)
 * live under `Infrastructure\Http\Form`, not under `Application\Command` or
 * `Application\Query`, so the subject selector never reaches them — no
 * exclusion is needed. CLAUDE.md documents their mutability as the one
 * explicit exception to "immutable by default" ("Symfony Form-bound input
 * DTOs ... MUST be final but NOT readonly — the framework writes to
 * properties via reflection during handleRequest").
 *
 * Across the 91 subject classes the only import outside their own context's
 * `Application` namespace is the built-in `DateTimeImmutable` (the
 * `Symfony\Component\Messenger` imports in this namespace live in the
 * excluded `ReleasesHouseholdEvents` trait), so the framework-dependency
 * assertion below is also a zero baseline.
 */
final class CommandAndQueryDtoPurityRule
{
    private const COMMAND_OR_QUERY_NAMESPACE = '/^App\\\\[A-Za-z]+\\\\Application\\\\(Command|Query)(\\\\|$)/';

    #[TestRule]
    public function command_and_query_dtos_do_not_depend_on_domain(): Rule
    {
        return PHPat::rule()
            ->classes($this->dtoSubject())
            ->shouldNot()
            ->dependOn()
            ->classes(Selector::inNamespace('/^App\\\\[A-Za-z]+\\\\Domain(\\\\|$)/', true))
            ->because('CLAUDE.md: DTOs carry primitives, not Domain value objects or entities.');
    }

    #[TestRule]
    public function command_and_query_dtos_do_not_depend_on_framework_types(): Rule
    {
        return PHPat::rule()
            ->classes($this->dtoSubject())
            ->shouldNot()
            ->dependOn()
            ->classes(
                Selector::inNamespace('Symfony'),
                Selector::inNamespace('Doctrine'),
                Selector::inNamespace('Psr'),
                Selector::inNamespace('Twig'),
            )
            ->because('CLAUDE.md: framework types are forbidden inside command/query DTOs so they stay '
                . 'trivially serializable.');
    }

    #[TestRule]
    public function command_and_query_dtos_are_readonly(): Rule
    {
        return PHPat::rule()
            ->classes($this->dtoSubject())
            ->should()
            ->beReadonly()
            ->because('CLAUDE.md: command/query DTOs are final with readonly constructor-promoted properties.');
    }

    private function dtoSubject(): SelectorInterface
    {
        return Selector::AllOf(
            Selector::inNamespace(self::COMMAND_OR_QUERY_NAMESPACE, true),
            Selector::Not(Selector::classname('/Handler$/', true)),
            Selector::Not(Selector::isInterface()),
            Selector::Not(Selector::isEnum()),
            Selector::Not(Selector::isTrait()),
        );
    }
}
