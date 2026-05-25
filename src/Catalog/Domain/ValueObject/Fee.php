<?php

declare(strict_types=1);

namespace App\Catalog\Domain\ValueObject;

use App\Catalog\Domain\Exception\InvalidFee;

/**
 * A single labelled charge attached to a Listing (e.g. "Adult", "Member",
 * "Drop-in"). A Listing may carry several fees so staff can quote the
 * matching price at the POS without selecting a separate SKU per
 * audience.
 */
final readonly class Fee
{
    public Money $amount;

    public string $label;

    private function __construct(Money $amount, string $label)
    {
        $this->amount = $amount;
        $this->label = $label;
    }

    public static function of(Money $amount, string $label): self
    {
        $trimmed = trim($label);

        if ($trimmed === '') {
            throw InvalidFee::emptyLabel();
        }

        $length = mb_strlen($trimmed, 'UTF-8');

        if ($length > InvalidFee::MAX_LABEL_LENGTH) {
            throw InvalidFee::labelTooLong($length);
        }

        return new self($amount, $trimmed);
    }

    public function equals(self $other): bool
    {
        return $this->label === $other->label
            && $this->amount->equals($other->amount);
    }
}
