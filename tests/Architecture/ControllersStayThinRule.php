<?php

declare(strict_types=1);

namespace App\Tests\Architecture;

use PHPat\Selector\Selector;
use PHPat\Test\Attributes\TestRule;
use PHPat\Test\Builder\Rule;
use PHPat\Test\PHPat;

/**
 * Controllers never depend on Doctrine directly (CLAUDE.md: "Forbid in
 * controllers: calls to EntityManagerInterface, Repository::find*, ...").
 * deptrac already grants whole *\Infrastructure layers access to Doctrine
 * for repository implementations; this rule narrows that grant for the
 * Http slice.
 *
 * The namespace regex requires exactly one segment between "App" and
 * "Infrastructure\Http" (\w+ cannot cross a "\" boundary), so it matches
 * App\<Context>\Infrastructure\Http\... but not the deeper
 * App\Tests\Unit\<Context>\Infrastructure\Http\... test namespaces.
 *
 * The subject also covers the seven root App\Controller classes (LRA-13
 * legacy controllers awaiting reassignment to the Reports/Scheduling
 * bounded contexts): deptrac's Legacy layer grants that namespace Doctrine
 * too, and without this rule nothing else would stop an
 * EntityManagerInterface injection from landing there. None import
 * Doctrine today, so this keeps the zero baseline intact.
 */
final class ControllersStayThinRule
{
    private const CONTEXT_HTTP_NAMESPACE = '#^App\\\\\w+\\\\Infrastructure\\\\Http#';
    private const ROOT_CONTROLLER_NAMESPACE = 'App\Controller';

    #[TestRule]
    public function controllers_do_not_depend_on_doctrine(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::AnyOf(
                Selector::inNamespace(self::CONTEXT_HTTP_NAMESPACE, true),
                Selector::inNamespace(self::ROOT_CONTROLLER_NAMESPACE),
            ))
            ->shouldNot()
            ->dependOn()
            ->classes(Selector::inNamespace('Doctrine'))
            ->because('CLAUDE.md: forbid calls to EntityManagerInterface/Doctrine from controllers.');
    }
}
