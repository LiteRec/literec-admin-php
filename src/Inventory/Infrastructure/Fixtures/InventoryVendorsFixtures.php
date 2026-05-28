<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Fixtures;

use App\Inventory\Application\Command\RegisterVendor;
use App\Inventory\Domain\ValueObject\VendorId;
use App\Shared\Infrastructure\Fixtures\FixtureReferenceRegistry;
use App\Shared\Infrastructure\Fixtures\HandledResult;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Seeds five curated Inventory vendors via the {@see RegisterVendor}
 * command bus dispatch (LRA-92).
 *
 * Vendors are referenced by downstream fixtures (purchase orders,
 * inventory items with primaryVendorId). The minted {@see VendorId}
 * for each curated vendor is stashed on the fixtures reference
 * registry under {@see referenceKey()} so siblings in the same
 * fixture chain can fetch them without re-querying the database.
 */
final class InventoryVendorsFixtures extends Fixture implements FixtureGroupInterface, DependentFixtureInterface
{
    public const REFERENCE_PREFIX = 'inventory.vendor.';

    /**
     * @var list<array{
     *     code: string,
     *     name: string,
     *     contact: string,
     *     email: string,
     *     phone: string,
     *     address: array{
     *         street: string,
     *         unit: ?string,
     *         city: string,
     *         state: string,
     *         postalCode: string,
     *         country: string,
     *     },
     * }>
     */
    private const VENDORS = [
        [
            'code' => 'ACME-SUPPLY',
            'name' => 'Acme Supply Co.',
            'contact' => 'Alice Anderson',
            'email' => 'orders@acme-supply.test',
            'phone' => '+1-555-0201',
            'address' => [
                'street' => '100 Industrial Way',
                'unit' => null,
                'city' => 'Springfield',
                'state' => 'IL',
                'postalCode' => '62701',
                'country' => 'US',
            ],
        ],
        [
            'code' => 'BLUE-PEAK',
            'name' => 'Blue Peak Distributors',
            'contact' => 'Brian Brooks',
            'email' => 'sales@bluepeak.test',
            'phone' => '+1-555-0202',
            'address' => [
                'street' => '250 Harbor Blvd',
                'unit' => 'Suite 4',
                'city' => 'Seattle',
                'state' => 'WA',
                'postalCode' => '98101',
                'country' => 'US',
            ],
        ],
        [
            'code' => 'CORE-FOODS',
            'name' => 'Core Foods Inc.',
            'contact' => 'Casey Chen',
            'email' => 'fulfillment@corefoods.test',
            'phone' => '+1-555-0203',
            'address' => [
                'street' => '12 Market Square',
                'unit' => null,
                'city' => 'Boston',
                'state' => 'MA',
                'postalCode' => '02108',
                'country' => 'US',
            ],
        ],
        [
            'code' => 'DELTA-LOGISTICS',
            'name' => 'Delta Logistics',
            'contact' => 'Dana Diaz',
            'email' => 'ops@delta-logistics.test',
            'phone' => '+1-555-0204',
            'address' => [
                'street' => '8800 Cargo Lane',
                'unit' => null,
                'city' => 'Atlanta',
                'state' => 'GA',
                'postalCode' => '30303',
                'country' => 'US',
            ],
        ],
        [
            'code' => 'EVERGREEN-GOODS',
            'name' => 'Evergreen Goods',
            'contact' => 'Erin Estrada',
            'email' => 'wholesale@evergreen.test',
            'phone' => '+1-555-0205',
            'address' => [
                'street' => '47 Pinecone Rd',
                'unit' => null,
                'city' => 'Portland',
                'state' => 'OR',
                'postalCode' => '97201',
                'country' => 'US',
            ],
        ],
    ];

    public function __construct(
        private readonly MessageBusInterface $commandBus,
        private readonly FixtureReferenceRegistry $references,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        foreach (self::VENDORS as $row) {
            $envelope = $this->commandBus->dispatch(new RegisterVendor(
                code: $row['code'],
                name: $row['name'],
                contact: $row['contact'],
                email: $row['email'],
                phone: $row['phone'],
                address: $row['address'],
            ));

            $vendorId = HandledResult::from($envelope, VendorId::class);
            $this->references->set(self::referenceKey($row['code']), $vendorId);
        }
    }

    public function getDependencies(): array
    {
        return [InventoryBaseFixtures::class];
    }

    public static function getGroups(): array
    {
        return ['inventory-vendors', 'dev', 'test', 'demo'];
    }

    public static function referenceKey(string $vendorCode): string
    {
        return self::REFERENCE_PREFIX . $vendorCode;
    }

    /**
     * Convenience accessor for downstream fixtures: returns the codes
     * of the curated vendors in the order they were dispatched.
     *
     * @return list<string>
     */
    public static function vendorCodes(): array
    {
        return array_map(
            static fn (array $row): string => $row['code'],
            self::VENDORS,
        );
    }
}
