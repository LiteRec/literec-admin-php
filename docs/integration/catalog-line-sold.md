# Catalog `LineSold` integration event

## Purpose

`LineSold` is the published-language event the **Transactions** bounded context will raise once it ships, signalling that a Catalog listing has been sold on a specific transaction. Downstream contexts react to the sale without coupling to Catalog or Transactions internals:

- **Inventory** decrements on-hand stock for `INVENTORY` listings.
- **Programs**, **Memberships**, **Rentals**, future **GiftCards** activate the matching entitlement.

The event itself is owned by Catalog because the data shape pivots on Catalog primitives (`listingId`, `listingKind`, `listingCode`); Catalog defines the contract, the Transactions context (future ticket) emits it.

## Class location

`src/Catalog/Integration/Event/LineSold.php` (under the `App\Catalog\Integration\Event\` namespace).

The `CatalogIntegration` Deptrac layer depends on nothing — consumers reference this class without pulling Catalog internals.

## Field contract

| Field | Type | Notes |
|---|---|---|
| `listingId` | `string` | UUID v7 of the sold listing. Always present, never empty. |
| `listingKind` | `string` | One of the `ListingKind` enum values (`INVENTORY`, `PROGRAM`, `MEMBERSHIP`, `RENTAL`, `GIFT_CARD`). New cases are additive. |
| `listingCode` | `string` | Canonical (uppercased) listing code. Always present. |
| `quantity` | `int` | Number of units sold on this line. Always `>= 1`. |
| `facilityCode` | `string` | The facility where the sale occurred. Always present. |
| `transactionId` | `string` | The owning Transactions context aggregate id. Always present. |
| `occurredAt` | `\DateTimeImmutable` | When the sale was recorded by the producer. |

All fields are `public readonly`. The constructor validates non-empty strings and `quantity >= 1`; malformed payloads raise `\InvalidArgumentException` at the boundary.

## Delivery semantics

- **Post-commit dispatch.** Producers MUST publish the event through the `event.bus` with `Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp`. Combined with the `command.bus` `doctrine_transaction` middleware, the envelope reaches the async transport only after the writing transaction commits. If the command rolls back, the envelope is discarded — `tests/Functional/Catalog/Integration/LineSoldPostCommitTest` pins this behaviour.
- **Async transport.** The event is routed to the `async` transport (`config/packages/messenger.yaml`). In test, the transport is replaced with `in-memory://` so contract tests can inspect what was published without a broker.
- **At-least-once.** The Messenger retry strategy may redeliver. Consumers MUST be idempotent keyed on `(transactionId, listingId)`; a (transaction, listing) pair MUST produce the same downstream effect on every redelivery.

## Subscribing

A downstream consumer adds a handler under its own Infrastructure namespace:

```php
#[AsMessageHandler]
final class HandleLineSold
{
    public function __invoke(LineSold $event): void
    {
        // Idempotent reaction keyed on ($event->transactionId, $event->listingId).
    }
}
```

No additional Catalog-side wiring is required.

## Backwards-compatibility policy

- **Additive only.** New fields may be added with sensible defaults. Existing fields MUST NOT be renamed, repurposed, removed, or have their semantics changed.
- **Breaking changes get a new class.** If the producer ever needs an incompatible shape, introduce `LineSoldV2` (a new class in the same namespace); leave `LineSold` in place and migrate consumers deliberately.
- **No type changes.** `quantity` will not become `float`, `occurredAt` will not become a string. The contract is the wire format.

## Pinned tests

- `tests/Unit/Catalog/Integration/Event/LineSoldTest.php` — constructor invariants.
- `tests/Functional/Catalog/Integration/LineSoldPostCommitTest.php` — routing + rollback-suppresses-publication.
