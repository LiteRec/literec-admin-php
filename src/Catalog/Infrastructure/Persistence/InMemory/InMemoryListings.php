<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Persistence\InMemory;

use App\Catalog\Domain\Exception\DuplicateListingCode;
use App\Catalog\Domain\Exception\ListingNotFound;
use App\Catalog\Domain\Listing;
use App\Catalog\Domain\ListingKind;
use App\Catalog\Domain\Listings;
use App\Catalog\Domain\ValueObject\ListingCode;
use App\Catalog\Domain\ValueObject\ListingId;

/**
 * Array-backed adapter for the {@see Listings} port. Used by unit and
 * contract tests so the Domain layer does not require a live database.
 */
final class InMemoryListings implements Listings
{
    /** @var array<string, Listing> keyed by listing id string */
    private array $byId = [];

    public function add(Listing $listing): void
    {
        $codeValue = $listing->code()->value;

        foreach ($this->byId as $existing) {
            if ($existing->code()->value === $codeValue && ! $existing->id()->equals($listing->id())) {
                throw DuplicateListingCode::for($codeValue);
            }
        }

        $this->byId[$listing->id()->value] = $listing;
    }

    public function save(Listing $listing): void
    {
        // save() only persists updates to aggregates that already exist;
        // new aggregates must go through add().
        if (! isset($this->byId[$listing->id()->value])) {
            throw ListingNotFound::byId($listing->id()->value);
        }

        $this->byId[$listing->id()->value] = $listing;
    }

    public function byId(ListingId $id): Listing
    {
        if (! isset($this->byId[$id->value])) {
            throw ListingNotFound::byId($id->value);
        }

        return $this->byId[$id->value];
    }

    public function byCode(ListingCode $code): Listing
    {
        foreach ($this->byId as $listing) {
            if ($listing->code()->equals($code)) {
                return $listing;
            }
        }

        throw ListingNotFound::byCode($code->value);
    }

    public function existsWithCode(ListingCode $code): bool
    {
        foreach ($this->byId as $listing) {
            if ($listing->code()->equals($code)) {
                return true;
            }
        }

        return false;
    }

    public function findByKind(ListingKind $kind, int $offset, int $limit): array
    {
        $matching = array_values(array_filter(
            $this->byId,
            static fn(Listing $l): bool => $l->kind() === $kind,
        ));

        // Match the Doctrine adapter's ordering so the shared contract
        // test passes against both implementations.
        usort(
            $matching,
            static fn(Listing $a, Listing $b): int => $a->code()->value <=> $b->code()->value,
        );

        return array_slice($matching, $offset, $limit);
    }

    public function searchByName(string $query, int $offset, int $limit): array
    {
        $needle = mb_strtolower(trim($query), 'UTF-8');

        if ($needle === '') {
            return [];
        }

        $matching = array_values(array_filter(
            $this->byId,
            static fn(Listing $l): bool => str_contains(
                mb_strtolower($l->name(), 'UTF-8'),
                $needle,
            ),
        ));

        // Match the Doctrine adapter's ordering so the shared contract
        // test passes against both implementations.
        usort(
            $matching,
            static fn(Listing $a, Listing $b): int => $a->name() <=> $b->name(),
        );

        return array_slice($matching, $offset, $limit);
    }
}
