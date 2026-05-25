<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Persistence\InMemory;

use App\Inventory\Domain\Exception\DuplicateVendorCode;
use App\Inventory\Domain\Exception\VendorNotFound;
use App\Inventory\Domain\Vendor;
use App\Inventory\Domain\Vendors;
use App\Inventory\Domain\ValueObject\VendorCode;
use App\Inventory\Domain\ValueObject\VendorId;

/**
 * Array-backed adapter for the {@see Vendors} port. Used by unit and
 * contract tests so the Domain layer can exercise the port without
 * booting Symfony or hitting Postgres.
 */
final class InMemoryVendors implements Vendors
{
    /** @var array<string, Vendor> keyed by vendor id string */
    private array $byId = [];

    public function add(Vendor $vendor): void
    {
        $codeValue = $vendor->code()->value;

        foreach ($this->byId as $existing) {
            if ($existing->code()->value === $codeValue && ! $existing->id()->equals($vendor->id())) {
                throw DuplicateVendorCode::for($codeValue);
            }
        }

        $this->byId[$vendor->id()->value] = $vendor;
    }

    public function save(Vendor $vendor): void
    {
        if (! isset($this->byId[$vendor->id()->value])) {
            throw VendorNotFound::byId($vendor->id()->value);
        }

        $this->byId[$vendor->id()->value] = $vendor;
    }

    public function byId(VendorId $id): Vendor
    {
        if (! isset($this->byId[$id->value])) {
            throw VendorNotFound::byId($id->value);
        }

        return $this->byId[$id->value];
    }

    public function byCode(VendorCode $code): Vendor
    {
        foreach ($this->byId as $vendor) {
            if ($vendor->code()->equals($code)) {
                return $vendor;
            }
        }

        throw VendorNotFound::byCode($code->value);
    }

    public function existsWithCode(VendorCode $code): bool
    {
        foreach ($this->byId as $vendor) {
            if ($vendor->code()->equals($code)) {
                return true;
            }
        }

        return false;
    }

    public function searchByName(string $query, int $offset, int $limit): array
    {
        $needle = mb_strtolower(trim($query), 'UTF-8');

        if ($needle === '') {
            return [];
        }

        $matching = array_values(array_filter(
            $this->byId,
            static fn(Vendor $v): bool => str_contains(
                mb_strtolower($v->name()->value, 'UTF-8'),
                $needle,
            ),
        ));

        // Match the Doctrine adapter's ordering so the shared contract
        // test passes against both implementations.
        usort(
            $matching,
            static fn(Vendor $a, Vendor $b): int => $a->name()->value <=> $b->name()->value,
        );

        return array_slice($matching, $offset, $limit);
    }

    public function listActive(int $offset, int $limit): array
    {
        $matching = array_values(array_filter(
            $this->byId,
            static fn(Vendor $v): bool => ! $v->isArchived(),
        ));

        usort(
            $matching,
            static fn(Vendor $a, Vendor $b): int => $a->code()->value <=> $b->code()->value,
        );

        return array_slice($matching, $offset, $limit);
    }
}
