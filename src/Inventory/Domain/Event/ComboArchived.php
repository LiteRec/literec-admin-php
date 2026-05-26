<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Event;

use App\Inventory\Domain\ValueObject\ComboId;
use DateTimeImmutable;

final readonly class ComboArchived
{
    public function __construct(
        public ComboId $comboId,
        public DateTimeImmutable $occurredAt,
    ) {
    }
}
