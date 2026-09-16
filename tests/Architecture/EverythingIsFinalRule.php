<?php

declare(strict_types=1);

namespace App\Tests\Architecture;

use App\Kernel;
use PHPat\Selector\Selector;
use PHPat\Test\Attributes\TestRule;
use PHPat\Test\Builder\Rule;
use PHPat\Test\PHPat;

/**
 * Every concrete class in App is final by default (CLAUDE.md: "Declare every
 * concrete class final. If you think you need to extend it, define an
 * interface and inject an implementation instead.").
 *
 * App\Kernel is excluded because Symfony's MicroKernelTrait requires it to
 * stay extendable from HttpKernel\Kernel; interfaces, enums, traits, and
 * abstract classes are excluded because "final" does not apply to them.
 */
final class EverythingIsFinalRule
{
    #[TestRule]
    public function classes_in_app_are_final(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace('App'))
            ->excluding(
                Selector::inNamespace('App\Tests'),
                Selector::isInterface(),
                Selector::isEnum(),
                Selector::isTrait(),
                Selector::isAbstract(),
                Selector::classname(Kernel::class),
            )
            ->should()
            ->beFinal()
            ->because('CLAUDE.md: declare every concrete class final by default.');
    }
}
