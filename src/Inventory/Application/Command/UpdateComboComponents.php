<?php

declare(strict_types=1);

namespace App\Inventory\Application\Command;

/**
 * @phpstan-type ComponentInput array{itemId: string, quantityPerCombo: int}
 */
final readonly class UpdateComboComponents
{
    /**
     * @param list<ComponentInput> $components
     */
    public function __construct(
        public string $comboId,
        public array $components,
    ) {
    }
}
