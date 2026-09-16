<?php

declare(strict_types=1);

namespace App\Tests\Architecture;

use PHPat\Selector\Selector;
use PHPat\Test\Attributes\TestRule;
use PHPat\Test\Builder\Rule;
use PHPat\Test\PHPat;

/**
 * Every Doctrine repository implements a port from its own context's Domain
 * layer (CLAUDE.md: "Declare repository interfaces in Domain/ ... implement
 * them as final class DoctrineUsers implements Users ... this is the only
 * place EntityManagerInterface may appear").
 *
 * PHPat cannot backreference "the same context" between the subject and the
 * target selector, so this yields one rule per context — adding a context
 * means adding its name to {@see self::CONTEXTS} below.
 *
 * The subject namespace is anchored with a trailing `$` so it selects only
 * `App\<Context>\Infrastructure\Persistence\Doctrine` itself, not its
 * sub-namespaces: `Persistence\Doctrine\Type` (DBAL types extending
 * `Type`/`JsonType`), `Persistence\Doctrine\Read` (read models that
 * implement Application query ports, not Domain ports), and
 * `Persistence\Doctrine\Event` (lineage event handlers) are all out of
 * scope by construction.
 *
 * `Not(isThrowable())` excludes
 * `Households\Infrastructure\Persistence\Doctrine\MemberCodeAllocationFailed`,
 * which extends `\RuntimeException` on purpose — an infrastructure failure,
 * deliberately not a Domain marker — precedent:
 * `Inventory\Application\Exception\CrossBusRegistrationFailed`.
 */
final class DoctrineRepositoriesImplementDomainPortRule
{
    /** @var list<string> */
    private const CONTEXTS = ['Catalog', 'Households', 'Inventory', 'Users'];

    /**
     * @return iterable<string, Rule>
     */
    #[TestRule]
    public function doctrine_repositories_implement_their_context_domain_port(): iterable
    {
        foreach (self::CONTEXTS as $context) {
            yield $context => PHPat::rule()
                ->classes(Selector::AllOf(
                    Selector::inNamespace(
                        \sprintf('/^App\\\\%s\\\\Infrastructure\\\\Persistence\\\\Doctrine$/', $context),
                        true,
                    ),
                    Selector::Not(Selector::isThrowable()),
                ))
                ->should()
                ->implement()
                ->classes(Selector::inNamespace(\sprintf('/^App\\\\%s\\\\Domain(\\\\|$)/', $context), true))
                ->because('CLAUDE.md: Doctrine repositories implement a port declared by their own context\'s Domain.');
        }
    }
}
