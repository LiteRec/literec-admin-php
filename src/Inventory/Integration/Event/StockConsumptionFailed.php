<?php

declare(strict_types=1);

namespace App\Inventory\Integration\Event;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Published-language integration event raised by the Inventory ACL when
 * a Catalog {@see App\Catalog\Integration\Event\LineSold} cannot
 * successfully decrement stock — for example because the referenced
 * inventory item does not exist, an active item link blocks the sale,
 * or FIFO consumption runs out of stock.
 *
 * Contract:
 *   - Primitive-only fields so the envelope serialises onto any
 *     Messenger transport without leaking domain types.
 *   - Field set is additive-only.
 *   - At-least-once delivery via the async transport; consumers must
 *     dedupe keyed on (transactionId, listingId).
 *
 * The first outbound integration event in the Inventory bounded
 * context — the future Transactions context will subscribe to reverse
 * a sale whose stock side failed.
 */
final readonly class StockConsumptionFailed
{
    /**
     * Reason codes published by the Inventory ACL when consumption
     * fails. Additive-only — never repurpose or remove a value.
     */
    public const string REASON_UNKNOWN_INVENTORY_ITEM = 'UNKNOWN_INVENTORY_ITEM';
    public const string REASON_INSUFFICIENT_STOCK = 'INSUFFICIENT_STOCK';
    public const string REASON_LINK_VIOLATION = 'LINK_VIOLATION';

    public function __construct(
        public string $listingId,
        public string $transactionId,
        public string $facilityCode,
        public string $reasonCode,
        public ?string $offendingInventoryItemId,
        public ?string $offendingLinkId,
        public DateTimeImmutable $occurredAt,
    ) {
        if ($listingId === '') {
            throw new InvalidArgumentException('StockConsumptionFailed.listingId must not be empty.');
        }
        if ($transactionId === '') {
            throw new InvalidArgumentException('StockConsumptionFailed.transactionId must not be empty.');
        }
        if ($facilityCode === '') {
            throw new InvalidArgumentException('StockConsumptionFailed.facilityCode must not be empty.');
        }
        if (
            ! in_array(
                $reasonCode,
                [
                    self::REASON_UNKNOWN_INVENTORY_ITEM,
                    self::REASON_INSUFFICIENT_STOCK,
                    self::REASON_LINK_VIOLATION,
                ],
                true,
            )
        ) {
            throw new InvalidArgumentException(sprintf(
                'StockConsumptionFailed.reasonCode must be one of the defined REASON_* constants; got "%s".',
                $reasonCode,
            ));
        }
    }
}
