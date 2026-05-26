<?php

declare(strict_types=1);

namespace App\Inventory\Domain;

use App\Inventory\Domain\Exception\DuplicateVendorCode;
use App\Inventory\Domain\Exception\VendorNotFound;
use App\Inventory\Domain\ValueObject\VendorCode;
use App\Inventory\Domain\ValueObject\VendorId;

/**
 * Domain port for persisting and retrieving Vendor aggregates.
 *
 * Forbids generic finders (Doctrine `findBy`, `findOneBy`,
 * `createQueryBuilder` etc.); every accessor is named after a domain
 * question staff/admin users actually ask of the Inventory context.
 */
interface Vendors
{
    /**
     * Persists a newly registered vendor.
     *
     * @throws DuplicateVendorCode when a vendor with the same code
     *         already exists. Production adapters rely on the database's
     *         unique constraint so concurrent inserts cannot race past
     *         the in-process check.
     */
    public function add(Vendor $vendor): void;

    /**
     * Persists modifications to an existing vendor.
     *
     * @throws VendorNotFound when no vendor with the given id has been
     *         persisted yet. Use {@see add()} to register new vendors.
     */
    public function save(Vendor $vendor): void;

    /**
     * @throws VendorNotFound
     */
    public function byId(VendorId $id): Vendor;

    /**
     * @throws VendorNotFound
     */
    public function byCode(VendorCode $code): Vendor;

    public function existsWithCode(VendorCode $code): bool;

    /**
     * Case-insensitive partial match on the vendor name.
     *
     * Pagination preconditions (callers must normalise/validate at the
     * entry point — application services or HTTP controllers — before
     * dispatching; this contract does not re-validate at runtime):
     *   - $offset >= 0
     *   - $limit  >= 1
     *
     * @return list<Vendor>
     */
    public function searchByName(string $query, int $offset, int $limit): array;

    /**
     * Lists non-archived vendors ordered by code ASC.
     *
     * Pagination preconditions (callers must normalise/validate at the
     * entry point — application services or HTTP controllers — before
     * dispatching; this contract does not re-validate at runtime):
     *   - $offset >= 0
     *   - $limit  >= 1
     *
     * @return list<Vendor>
     */
    public function listActive(int $offset, int $limit): array;
}
