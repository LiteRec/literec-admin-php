<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Fixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Anchors the inventory-base group and declares the dependency on
 * Catalog so any --group=inventory-* load implicitly pulls catalog-base
 * first.
 *
 * Facilities themselves are just opaque string codes
 * ({@see \App\Inventory\Domain\ValueObject\FacilityCode}); there is no
 * Facility aggregate to register, so this fixture intentionally writes
 * no data. The two canonical facility codes used by downstream
 * fixtures are exposed as public constants so tests can assert
 * against the same values without re-declaring them.
 */
final class InventoryBaseFixtures extends Fixture implements FixtureGroupInterface, DependentFixtureInterface
{
    public const FACILITY_PRIMARY = 'FAC-A';
    public const FACILITY_SECONDARY = 'FAC-B';

    /** @var list<string> */
    public const FACILITIES = [self::FACILITY_PRIMARY, self::FACILITY_SECONDARY];

    public function load(ObjectManager $manager): void
    {
        // No-op. See class docblock.
    }

    public function getDependencies(): array
    {
        // String reference avoids a cross-namespace concrete import
        // inside the Domain/Application layers; the fixtures loader
        // resolves the dependency by FQCN string.
        return ['App\\Catalog\\Infrastructure\\Fixtures\\CatalogBaseFixtures'];
    }

    public static function getGroups(): array
    {
        return ['inventory-base', 'dev', 'test', 'demo'];
    }
}
