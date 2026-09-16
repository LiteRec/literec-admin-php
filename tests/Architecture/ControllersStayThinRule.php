<?php

declare(strict_types=1);

namespace App\Tests\Architecture;

use PHPat\Selector\Selector;
use PHPat\Test\Attributes\TestRule;
use PHPat\Test\Builder\Rule;
use PHPat\Test\PHPat;

/**
 * Controllers under a bounded context's Infrastructure\Http layer never
 * depend on Doctrine directly (CLAUDE.md: "Forbid in controllers: calls to
 * EntityManagerInterface, Repository::find*, ..."). deptrac already grants
 * whole *\Infrastructure layers access to Doctrine for repository
 * implementations; this rule narrows that grant for the Http slice.
 *
 * The namespace regex requires exactly one segment between "App" and
 * "Infrastructure\Http" (\w+ cannot cross a "\" boundary), so it matches
 * App\<Context>\Infrastructure\Http\... but not the deeper
 * App\Tests\Unit\<Context>\Infrastructure\Http\... test namespaces.
 */
final class ControllersStayThinRule
{
    private const CONTEXT_HTTP_NAMESPACE = '#^App\\\\\w+\\\\Infrastructure\\\\Http#';

    #[TestRule]
    public function controllers_do_not_depend_on_doctrine(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace(self::CONTEXT_HTTP_NAMESPACE, true))
            ->shouldNot()
            ->dependOn()
            ->classes(Selector::inNamespace('Doctrine'))
            ->because('CLAUDE.md: forbid calls to EntityManagerInterface/Doctrine from controllers.');
    }
}
