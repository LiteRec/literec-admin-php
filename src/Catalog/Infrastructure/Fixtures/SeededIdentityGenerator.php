<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Fixtures;

use App\Catalog\Domain\IdentityGenerator;
use App\Catalog\Domain\ValueObject\ListingId;
use App\Shared\Infrastructure\Fixtures\DeterministicUuid7Sequencer;
use Psr\Clock\ClockInterface;

/**
 * Deterministic {@see IdentityGenerator} for Catalog (LRA-92 fixtures).
 *
 * Mirrors {@see \App\Inventory\Infrastructure\Fixtures\SeededIdentityGenerator}
 * — the per-context port is duplicated by design (each bounded
 * context owns its own ID generator port so changes in one cannot
 * ripple into the other). Production wiring stays on
 * {@see \App\Catalog\Infrastructure\Identity\Uuid7IdentityGenerator}.
 */
final class SeededIdentityGenerator implements IdentityGenerator
{
    private readonly DeterministicUuid7Sequencer $sequencer;

    public function __construct(ClockInterface $clock, int $seed)
    {
        // Offset the Catalog seed from the Inventory seed so the two
        // contexts do not produce the same UUID sequence at the same
        // FIXTURE_SEED — if they did, a hypothetical bug crossing
        // listing_id ↔ inventory_item_id boundaries could silently
        // accept the wrong identifier in tests.
        $this->sequencer = new DeterministicUuid7Sequencer($clock, $seed + 1_000_000);
    }

    public function nextListingId(): ListingId
    {
        return ListingId::fromString($this->sequencer->next());
    }
}
