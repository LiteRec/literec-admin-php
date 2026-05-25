<?php

declare(strict_types=1);

namespace App\Catalog\Application\Query;

use App\Catalog\Application\Query\View\FeeView;
use App\Catalog\Application\Query\View\ListingSummaryView;
use App\Catalog\Domain\Listing;
use App\Catalog\Domain\Listings;
use App\Catalog\Domain\ValueObject\Fee;
use App\Catalog\Domain\ValueObject\ListingCode;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final class FindListingByCodeHandler
{
    public function __construct(
        private readonly Listings $listings,
    ) {
    }

    public function __invoke(FindListingByCode $query): ListingSummaryView
    {
        $listing = $this->listings->byCode(ListingCode::of($query->code));

        return self::toSummary($listing);
    }

    public static function toSummary(Listing $listing): ListingSummaryView
    {
        return new ListingSummaryView(
            id: $listing->id()->value,
            code: $listing->code()->value,
            kind: $listing->kind()->value,
            name: $listing->name(),
            archived: $listing->isArchived(),
        );
    }

    /**
     * @return list<FeeView>
     */
    public static function projectFees(Listing $listing): array
    {
        return array_map(
            static fn(Fee $fee): FeeView => new FeeView(
                amountCents: $fee->amount->cents,
                currency: $fee->amount->currency->value,
                label: $fee->label,
            ),
            $listing->fees(),
        );
    }
}
