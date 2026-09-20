<?php

declare(strict_types=1);

namespace App\Tests\Unit\Administration\Domain;

use App\Administration\Domain\Exception\InvalidPrivilegeDefinition;
use App\Administration\Domain\PrivilegeDefinition;
use App\Administration\Domain\PrivilegeGroup;
use App\Administration\Domain\PrivilegeRisk;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[Small]
final class PrivilegeDefinitionTest extends TestCase
{
    #[Test]
    #[TestWith([''], 'empty display name')]
    #[TestWith(['   '], 'whitespace-only display name')]
    #[TestDox('Rejects an empty (or whitespace-only) display name with InvalidPrivilegeDefinition.')]
    public function rejects_empty_display_name(string $displayName): void
    {
        $this->expectException(InvalidPrivilegeDefinition::class);

        self::assertInstanceOf(
            PrivilegeDefinition::class,
            new PrivilegeDefinition($displayName, 'A description.', PrivilegeGroup::Users, 0),
        );
    }

    #[Test]
    #[TestWith([''], 'empty description')]
    #[TestWith(['   '], 'whitespace-only description')]
    #[TestDox('Rejects an empty (or whitespace-only) description with InvalidPrivilegeDefinition.')]
    public function rejects_empty_description(string $description): void
    {
        $this->expectException(InvalidPrivilegeDefinition::class);

        self::assertInstanceOf(
            PrivilegeDefinition::class,
            new PrivilegeDefinition('Display Name', $description, PrivilegeGroup::Users, 0),
        );
    }

    #[Test]
    #[TestDox('Rejects a negative order with InvalidPrivilegeDefinition.')]
    public function rejects_negative_order(): void
    {
        $this->expectException(InvalidPrivilegeDefinition::class);

        self::assertInstanceOf(
            PrivilegeDefinition::class,
            new PrivilegeDefinition('Display Name', 'A description.', PrivilegeGroup::Users, -1),
        );
    }

    #[Test]
    #[TestDox('Risk defaults to PrivilegeRisk::Standard when not declared.')]
    public function risk_defaults_to_standard(): void
    {
        $definition = new PrivilegeDefinition('Display Name', 'A description.', PrivilegeGroup::Users, 0);

        self::assertSame(PrivilegeRisk::Standard, $definition->risk);
    }

    #[Test]
    #[TestDox('Constructs successfully with valid arguments and an explicit risk.')]
    public function constructs_with_valid_arguments(): void
    {
        $definition = new PrivilegeDefinition(
            'Display Name',
            'A description.',
            PrivilegeGroup::CashRegister,
            3,
            PrivilegeRisk::High,
        );

        self::assertSame('Display Name', $definition->displayName);
        self::assertSame('A description.', $definition->description);
        self::assertSame(PrivilegeGroup::CashRegister, $definition->group);
        self::assertSame(3, $definition->order);
        self::assertSame(PrivilegeRisk::High, $definition->risk);
    }
}
