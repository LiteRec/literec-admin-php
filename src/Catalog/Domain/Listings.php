<?php

declare(strict_types=1);

namespace App\Catalog\Domain;

use App\Catalog\Domain\Exception\DuplicateListingCode;
use App\Catalog\Domain\Exception\ListingNotFound;
use App\Catalog\Domain\ValueObject\ListingCode;
use App\Catalog\Domain\ValueObject\ListingId;

/**
 * Domain port for persisting and retrieving Listing aggregates.
 *
 * Forbids generic finders ({@see https://github.com/doctrine/orm} `findBy`,
 * `findOneBy`, `createQueryBuilder` etc.); every accessor is named after
 * a domain question staff/admin users actually ask of the Catalog.
 */
interface Listings
{
    /**
     * Persists a newly registered listing.
     *
     * @throws DuplicateListingCode when a listing with the same code
     *         already exists. Production adapters rely on the database's
     *         unique constraint so concurrent inserts cannot race past
     *         the in-process check.
     */
    public function add(Listing $listing): void;

    /**
     * Persists modifications to an existing listing.
     */
    public function save(Listing $listing): void;

    /**
     * @throws ListingNotFound
     */
    public function byId(ListingId $id): Listing;

    /**
     * @throws ListingNotFound
     */
    public function byCode(ListingCode $code): Listing;

    public function existsWithCode(ListingCode $code): bool;

    /**
     * @return list<Listing>
     */
    public function findByKind(ListingKind $kind, int $offset, int $limit): array;

    /**
     * Case-insensitive partial match on the listing name.
     *
     * @return list<Listing>
     */
    public function searchByName(string $query, int $offset, int $limit): array;
}
