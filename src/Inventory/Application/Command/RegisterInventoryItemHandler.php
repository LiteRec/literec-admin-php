<?php

declare(strict_types=1);

namespace App\Inventory\Application\Command;

use App\Catalog\Application\Command\RegisterListing;
use App\Catalog\Domain\ValueObject\ListingId;
use App\Inventory\Application\Exception\CrossBusRegistrationFailed;
use App\Inventory\Domain\IdentityGenerator;
use App\Inventory\Domain\InventoryItem;
use App\Inventory\Domain\InventoryItems;
use App\Inventory\Domain\ValueObject\PosColor;
use App\Inventory\Domain\ValueObject\ReorderThreshold;
use App\Inventory\Domain\ValueObject\VendorId;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use RuntimeException;
use Throwable;

/**
 * LRA-98 cross-bus orchestrator. Dispatches Catalog
 * {@see RegisterListing} on the same command.bus to obtain a fresh
 * {@see ListingId}, then constructs the Inventory aggregate bound to
 * that listing and persists it. The outer doctrine_transaction
 * middleware around the command bus wraps the whole envelope so
 * both writes commit or roll back together.
 *
 * The only cross-context import in Inventory code — explicitly
 * whitelisted in deptrac.yaml via the CatalogCrossBusCommands layer
 * (LRA-98).
 */
#[AsMessageHandler(bus: 'command.bus')]
final readonly class RegisterInventoryItemHandler
{
    public function __construct(
        private MessageBusInterface $commandBus,
        private InventoryItems $inventoryItems,
        private IdentityGenerator $identityGenerator,
        private ClockInterface $clock,
        private MessageBusInterface $eventBus,
    ) {
    }

    public function __invoke(RegisterInventoryItem $command): string
    {
        try {
            $listingId = $this->dispatchCatalogRegistration($command);

            $item = InventoryItem::register(
                id: $this->identityGenerator->nextInventoryItemId(),
                listingId: $listingId,
                primaryVendorId: $command->primaryVendorId !== null
                    ? VendorId::fromString($command->primaryVendorId)
                    : null,
                posColor: PosColor::ofHex($command->posColorHex),
                trackInventory: $command->trackInventory,
                rentable: $command->rentable,
                reorderThreshold: ReorderThreshold::ofUnits($command->reorderThresholdUnits),
                clock: $this->clock,
            );

            $this->inventoryItems->add($item);

            foreach ($item->releaseEvents() as $event) {
                $this->eventBus->dispatch($event, [new DispatchAfterCurrentBusStamp()]);
            }

            return $item->id()->value;
        } catch (Throwable $inner) {
            // Re-throw as the named exception so the LRA-86
            // controller can map the failure to a field-level form
            // error without sniffing for the underlying cause class.
            // The outer doctrine_transaction middleware sees the
            // exception bubble and rolls back both writes.
            throw CrossBusRegistrationFailed::fromInnerFailure($inner);
        }
    }

    private function dispatchCatalogRegistration(RegisterInventoryItem $command): ListingId
    {
        $envelope = $this->commandBus->dispatch(new RegisterListing(
            code: $command->code,
            kind: $command->kind,
            name: $command->name,
            fees: $command->fees,
            taxApply: $command->taxApply,
            taxIncludedInFee: $command->taxIncludedInFee,
            ledgerAccount: $command->ledgerAccount,
        ));

        $listingId = self::extractListingId($envelope);
        if ($listingId === null) {
            throw new RuntimeException(
                'RegisterListing handler returned no HandledStamp — cannot continue cross-bus registration.',
            );
        }

        return $listingId;
    }

    private static function extractListingId(Envelope $envelope): ?ListingId
    {
        /** @var HandledStamp|null $stamp */
        $stamp = $envelope->last(HandledStamp::class);
        if ($stamp === null) {
            return null;
        }

        $result = $stamp->getResult();
        if ($result instanceof ListingId) {
            return $result;
        }

        return null;
    }
}
