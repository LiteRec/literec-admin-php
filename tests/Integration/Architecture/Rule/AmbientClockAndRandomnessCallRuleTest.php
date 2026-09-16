<?php

declare(strict_types=1);

namespace App\Tests\Integration\Architecture\Rule;

use App\Tests\Architecture\Rule\AmbientClockAndRandomnessCallRule;
use App\Tests\Architecture\Rule\LayerNamespaces;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * @extends RuleTestCase<AmbientClockAndRandomnessCallRule>
 */
#[Medium]
#[Group('architecture')]
final class AmbientClockAndRandomnessCallRuleTest extends RuleTestCase
{
    private const CLOCK_TIP = 'Inject Psr\\Clock\\ClockInterface and call ->now() instead.';
    private const RANDOMNESS_TIP = 'Inject the context\'s IdentityGenerator port instead.';

    #[Test]
    #[TestDox(
        'Reports ambient clock/randomness functions and Symfony Uid static calls from Domain or Application, '
            . 'not calls through an injected port or the same call from Infrastructure.',
    )]
    public function it_reports_ambient_clock_and_randomness_calls_in_domain_or_application(): void
    {
        $this->analyse([__DIR__ . '/data/ambient-calls.php'], [
            ['time() reads the ambient system clock.', 30, self::CLOCK_TIP],
            ['date() reads the ambient system clock.', 31, self::CLOCK_TIP],
            ['strtotime() reads the ambient system clock.', 32, self::CLOCK_TIP],
            ['mktime() reads the ambient system clock.', 33, self::CLOCK_TIP],
            ['uniqid() reads an ambient randomness source.', 34, self::RANDOMNESS_TIP],
            ['random_int() reads an ambient randomness source.', 35, self::RANDOMNESS_TIP],
            ['array_rand() reads an ambient randomness source.', 36, self::RANDOMNESS_TIP],
            ['now() reads the ambient system clock.', 37, self::CLOCK_TIP],
            [
                'Symfony\\Component\\Uid\\Uuid generates an identifier from an ambient randomness source.',
                38,
                self::RANDOMNESS_TIP,
            ],
            [
                'Symfony\\Component\\Uid\\UuidV7 generates an identifier from an ambient randomness source.',
                39,
                self::RANDOMNESS_TIP,
            ],
            [
                'Symfony\\Component\\Clock\\Clock is a static clock singleton.',
                40,
                self::CLOCK_TIP,
            ],
        ]);
    }

    protected function getRule(): Rule
    {
        return new AmbientClockAndRandomnessCallRule(new LayerNamespaces());
    }
}
