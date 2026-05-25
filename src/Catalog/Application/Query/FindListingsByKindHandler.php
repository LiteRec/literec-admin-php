<?php

declare(strict_types=1);

namespace App\Catalog\Application\Query;

use App\Catalog\Application\Query\View\ListingSummaryView;
use App\Catalog\Domain\Listing;
use App\Catalog\Domain\ListingKind;
use App\Catalog\Domain\Listings;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final class FindListingsByKindHandler
{
    public function __construct(
        private readonly Listings $listings,
    ) {
    }

    /**
     * @return list<ListingSummaryView>
     */
    public function __invoke(FindListingsByKind $query): array
    {
        $rows = $this->listings->findByKind(
            ListingKind::from($query->kind),
            $query->offset,
            $query->limit,
        );

        return array_map(
            static fn(Listing $listing): ListingSummaryView => FindListingByCodeHandler::toSummary($listing),
            $rows,
        );
    }
}
