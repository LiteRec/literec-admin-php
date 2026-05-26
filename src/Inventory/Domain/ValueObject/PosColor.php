<?php

declare(strict_types=1);

namespace App\Inventory\Domain\ValueObject;

use App\Inventory\Domain\Exception\InvalidPosColor;

/**
 * 7-character #RRGGBB hex color used by the POS to render an
 * InventoryItem's button. Stored in canonical uppercase form.
 *
 * Palette enforcement (limiting POS colors to a fixed set) is a UI
 * concern and lives in the Twig view layer; the domain only validates
 * the hex format.
 */
final readonly class PosColor
{
    private const HEX_PATTERN = '/^#[0-9A-F]{6}$/';

    private const DEFAULT_HEX = '#FFFFFF';

    public string $hex;

    private function __construct(string $hex)
    {
        $normalized = strtoupper($hex);

        if (preg_match(self::HEX_PATTERN, $normalized) !== 1) {
            throw InvalidPosColor::for($hex);
        }

        $this->hex = $normalized;
    }

    public static function ofHex(string $hex): self
    {
        return new self($hex);
    }

    public static function default(): self
    {
        return new self(self::DEFAULT_HEX);
    }

    public function equals(self $other): bool
    {
        return $this->hex === $other->hex;
    }
}
