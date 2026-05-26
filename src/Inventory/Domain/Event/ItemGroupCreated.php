<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Event;

use App\Inventory\Domain\ValueObject\FacilityScope;
use App\Inventory\Domain\ValueObject\ItemGroupId;
use App\Inventory\Domain\ValueObject\ItemGroupName;
use App\Inventory\Domain\ValueObject\PosColor;
use DateTimeImmutable;

final readonly class ItemGroupCreated
{
    public function __construct(
        public ItemGroupId $itemGroupId,
        public ItemGroupName $name,
        public PosColor $color,
        public FacilityScope $facilityScope,
        public DateTimeImmutable $occurredAt,
    ) {
    }
}
