<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Event;

use App\Inventory\Domain\ValueObject\ComboComponent;
use App\Inventory\Domain\ValueObject\ComboId;
use DateTimeImmutable;

final readonly class ComboComponentsUpdated
{
    /**
     * @param list<ComboComponent> $components
     */
    public function __construct(
        public ComboId $comboId,
        public array $components,
        public DateTimeImmutable $occurredAt,
    ) {
    }
}
