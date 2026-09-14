<?php

declare(strict_types=1);

namespace App\Ui\CashRegister;

/**
 * One household member shown as a pill radio row in the Participant card.
 * Presentation-only sample data.
 */
final readonly class ParticipantOption
{
    public function __construct(
        public string $name,
        public string $memberCode,
        public bool $selected = false,
    ) {
    }
}
