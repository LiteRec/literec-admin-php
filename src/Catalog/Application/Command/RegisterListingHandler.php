<?php

declare(strict_types=1);

namespace App\Catalog\Application\Command;

use App\Catalog\Domain\Exception\DuplicateListingCode;
use App\Catalog\Domain\IdentityGenerator;
use App\Catalog\Domain\Listing;
use App\Catalog\Domain\ListingKind;
use App\Catalog\Domain\Listings;
use App\Catalog\Domain\ValueObject\Currency;
use App\Catalog\Domain\ValueObject\Fee;
use App\Catalog\Domain\ValueObject\LedgerAccount;
use App\Catalog\Domain\ValueObject\ListingCode;
use App\Catalog\Domain\ValueObject\ListingId;
use App\Catalog\Domain\ValueObject\Money;
use App\Catalog\Domain\ValueObject\TaxTreatment;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;

#[AsMessageHandler(bus: 'command.bus')]
final class RegisterListingHandler
{
    public function __construct(
        private readonly Listings $listings,
        private readonly IdentityGenerator $ids,
        private readonly ClockInterface $clock,
        private readonly MessageBusInterface $eventBus,
    ) {
    }

    public function __invoke(RegisterListing $command): ListingId
    {
        $code = ListingCode::of($command->code);

        if ($this->listings->existsWithCode($code)) {
            throw DuplicateListingCode::for($code->value);
        }

        $kind = ListingKind::from($command->kind);
        $fees = self::buildFees($command->fees);
        $tax = TaxTreatment::of($command->taxApply, $command->taxIncludedInFee);
        $ledger = LedgerAccount::of($command->ledgerAccount);
        $id = $this->ids->nextListingId();

        $listing = Listing::register(
            $id,
            $code,
            $kind,
            $command->name,
            $fees,
            $tax,
            $ledger,
            $this->clock,
        );

        $this->listings->add($listing);

        foreach ($listing->releaseEvents() as $event) {
            $this->eventBus->dispatch($event, [new DispatchAfterCurrentBusStamp()]);
        }

        return $id;
    }

    /**
     * @param list<array{amountCents: int, currency: string, label: string}> $rows
     *
     * @return list<Fee>
     */
    private static function buildFees(array $rows): array
    {
        $fees = [];
        foreach ($rows as $row) {
            $fees[] = Fee::of(
                Money::ofCents($row['amountCents'], Currency::from($row['currency'])),
                $row['label'],
            );
        }

        return $fees;
    }
}
