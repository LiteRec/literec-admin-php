<?php

declare(strict_types=1);

namespace App\Catalog\Application\Query;

use App\Catalog\Application\Query\View\ListingDetailView;
use App\Catalog\Domain\Listings;
use App\Catalog\Domain\ValueObject\ListingId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final class GetListingDetailHandler
{
    public function __construct(
        private readonly Listings $listings,
    ) {
    }

    public function __invoke(GetListingDetail $query): ListingDetailView
    {
        $listing = $this->listings->byId(ListingId::fromString($query->listingId));

        return new ListingDetailView(
            id: $listing->id()->value,
            code: $listing->code()->value,
            kind: $listing->kind()->value,
            name: $listing->name(),
            fees: FindListingByCodeHandler::projectFees($listing),
            taxApply: $listing->taxTreatment()->applyTax,
            taxIncludedInFee: $listing->taxTreatment()->taxIncludedInFee,
            ledgerAccount: $listing->ledgerAccount()->value,
            archived: $listing->isArchived(),
            registeredAt: $listing->registeredAt(),
            updatedAt: $listing->updatedAt(),
        );
    }
}
