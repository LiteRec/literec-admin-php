<?php

declare(strict_types=1);

namespace App\Tests\Support\Fake;

use App\Catalog\Domain\ValueObject\ListingId;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

/**
 * Synchronous MessageBus double for the LRA-98 cross-bus tests.
 * Returns the given {@see ListingId} from a HandledStamp on every
 * dispatch, mirroring what Catalog's RegisterListingHandler would
 * have produced when wired into the real Messenger middleware stack.
 */
final readonly class StubCatalogCommandBus implements MessageBusInterface
{
    public function __construct(private ListingId $listingId)
    {
    }

    public function dispatch(object $message, array $stamps = []): Envelope
    {
        return new Envelope($message, [new HandledStamp($this->listingId, 'catalog.register-listing-handler')]);
    }
}
