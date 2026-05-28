<?php

declare(strict_types=1);

namespace App\Inventory\Application\Command;

use App\Inventory\Domain\Exception\DuplicateVendorCode;
use App\Inventory\Domain\IdentityGenerator;
use App\Inventory\Domain\ValueObject\VendorAddress;
use App\Inventory\Domain\ValueObject\VendorCode;
use App\Inventory\Domain\ValueObject\VendorContact;
use App\Inventory\Domain\ValueObject\VendorId;
use App\Inventory\Domain\ValueObject\VendorName;
use App\Inventory\Domain\Vendor;
use App\Inventory\Domain\Vendors;
use App\Shared\Domain\ValueObject\EmailAddress;
use App\Shared\Domain\ValueObject\PhoneNumber;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;

/**
 * Handles {@see RegisterVendor} (LRA-92).
 *
 * Translates primitive input into the Vendor aggregate's value objects,
 * enforces the unique-code invariant in-process before relying on the
 * Postgres unique constraint as the race-safe fallback, persists the
 * aggregate, and dispatches the buffered {@see App\Inventory\Domain\Event\VendorRegistered}
 * event post-commit via the doctrine_transaction middleware on
 * command.bus.
 *
 * Returns the freshly minted {@see VendorId} so fixture loaders (and any
 * future HTTP form/controller) can chain follow-up commands such as
 * {@see CreatePurchaseOrder} without an additional read query.
 */
#[AsMessageHandler(bus: 'command.bus')]
final class RegisterVendorHandler
{
    public function __construct(
        private readonly Vendors $vendors,
        private readonly IdentityGenerator $ids,
        private readonly ClockInterface $clock,
        private readonly MessageBusInterface $eventBus,
    ) {
    }

    public function __invoke(RegisterVendor $command): VendorId
    {
        $code = VendorCode::fromString($command->code);

        if ($this->vendors->existsWithCode($code)) {
            throw DuplicateVendorCode::for($code->value);
        }

        $id = $this->ids->nextVendorId();

        $vendor = Vendor::register(
            $id,
            $code,
            VendorName::of($command->name),
            VendorContact::of($command->contact),
            $command->email !== null ? EmailAddress::of($command->email) : null,
            $command->phone !== null ? PhoneNumber::of($command->phone) : null,
            self::buildAddress($command->address),
            $this->clock,
        );

        $this->vendors->add($vendor);

        foreach ($vendor->releaseEvents() as $event) {
            $this->eventBus->dispatch($event, [new DispatchAfterCurrentBusStamp()]);
        }

        return $id;
    }

    /**
     * Expected shape (validated at runtime so a malformed bus payload
     * produces a controlled InvalidArgumentException rather than an
     * undefined-array-key notice):
     *   street, city, state, postalCode, country: non-empty string
     *   unit: string|null
     *
     * @param array<string, mixed>|null $row
     */
    private static function buildAddress(?array $row): ?VendorAddress
    {
        if ($row === null) {
            return null;
        }

        foreach (['street', 'city', 'state', 'postalCode', 'country'] as $required) {
            if (! array_key_exists($required, $row) || ! is_string($row[$required])) {
                throw new \InvalidArgumentException(
                    sprintf('Vendor address missing or invalid field: %s', $required),
                );
            }
        }
        $unit = array_key_exists('unit', $row) && is_string($row['unit'])
            ? $row['unit']
            : null;

        return VendorAddress::of(
            $row['street'],
            $unit,
            $row['city'],
            $row['state'],
            $row['postalCode'],
            $row['country'],
        );
    }
}
