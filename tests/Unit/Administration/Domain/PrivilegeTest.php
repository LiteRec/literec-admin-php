<?php

declare(strict_types=1);

namespace App\Tests\Unit\Administration\Domain;

use App\Administration\Domain\Privilege;
use App\Administration\Domain\PrivilegeGroup;
use App\Administration\Domain\PrivilegeRisk;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[Small]
final class PrivilegeTest extends TestCase
{
    /**
     * @return Generator<string, array{Privilege}>
     */
    public static function everyCase(): Generator
    {
        foreach (Privilege::cases() as $case) {
            yield $case->name => [$case];
        }
    }

    #[Test]
    #[DataProvider('everyCase')]
    #[TestDox('Every case returns a definition with a non-empty display name and description: $_dataName.')]
    public function every_case_has_display_metadata(Privilege $privilege): void
    {
        $definition = $privilege->definition();

        self::assertNotSame('', trim($definition->displayName));
        self::assertNotSame('', trim($definition->description));
    }

    #[Test]
    #[DataProvider('everyCase')]
    #[TestDox('Every case\'s backing value matches the legacy SCREAMING_SNAKE shape: $_dataName.')]
    public function every_case_backing_value_matches_screaming_snake(Privilege $privilege): void
    {
        self::assertMatchesRegularExpression('/^[A-Z][A-Z0-9_]*$/', $privilege->value);
    }

    #[Test]
    #[DataProvider('everyCase')]
    #[TestDox('Every case\'s group is a real PrivilegeGroup case: $_dataName.')]
    public function every_case_group_is_a_privilege_group(Privilege $privilege): void
    {
        self::assertContains($privilege->definition()->group, PrivilegeGroup::cases());
    }

    #[Test]
    #[DataProvider('everyCase')]
    #[TestDox('Every case reports a PrivilegeRisk: $_dataName.')]
    public function every_case_reports_a_risk(Privilege $privilege): void
    {
        self::assertContains($privilege->definition()->risk, PrivilegeRisk::cases());
    }

    #[Test]
    #[TestDox('Backing values are unique across the whole catalogue.')]
    public function backing_values_are_unique(): void
    {
        $values = array_map(static fn (Privilege $privilege): string => $privilege->value, Privilege::cases());

        self::assertCount(count($values), array_unique($values));
    }

    #[Test]
    #[TestDox('Pins the high-risk set so a later edit cannot quietly drop a privilege out of the audit stream.')]
    public function pins_the_high_risk_set(): void
    {
        $highRisk = array_map(
            static fn (Privilege $privilege): string => $privilege->name,
            array_values(array_filter(
                Privilege::cases(),
                static fn (Privilege $privilege): bool => $privilege->definition()->risk === PrivilegeRisk::High,
            )),
        );

        sort($highRisk);

        self::assertSame(
            [
                'CrGlEntry',
                'OverrideInventoryFees',
                'OverrideMembershipFees',
                'OverrideProgramFees',
                'OverrideReservationFees',
                'PosRefunds',
            ],
            $highRisk,
        );
    }
}
