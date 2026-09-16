<?php

declare(strict_types=1);

namespace App\Tests\Integration\Architecture\Rule;

use App\Tests\Architecture\Rule\AmbientDateTimeConstructionRule;
use App\Tests\Architecture\Rule\LayerNamespaces;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * @extends RuleTestCase<AmbientDateTimeConstructionRule>
 */
#[Medium]
#[Group('architecture')]
final class AmbientDateTimeConstructionRuleTest extends RuleTestCase
{
    private const AMBIENT_CLOCK_TIP = 'Inject Psr\\Clock\\ClockInterface and call ->now() instead.';

    #[Test]
    #[TestDox(
        'Reports DateTime(Immutable) construction with no argument or a "now" argument from Domain or '
            . 'Application, not the ISO-string carve-out or Infrastructure code.',
    )]
    public function it_reports_ambient_datetime_construction_in_domain_or_application(): void
    {
        $this->analyse([__DIR__ . '/data/ambient-datetime.php'], [
            [
                'DateTimeImmutable constructs the current moment from the ambient system clock.',
                21,
                self::AMBIENT_CLOCK_TIP,
            ],
            [
                'DateTime constructs the current moment from the ambient system clock.',
                22,
                self::AMBIENT_CLOCK_TIP,
            ],
            [
                'DateTimeImmutable constructs the current moment from the ambient system clock.',
                23,
                self::AMBIENT_CLOCK_TIP,
            ],
        ]);
    }

    protected function getRule(): Rule
    {
        return new AmbientDateTimeConstructionRule(new LayerNamespaces());
    }
}
