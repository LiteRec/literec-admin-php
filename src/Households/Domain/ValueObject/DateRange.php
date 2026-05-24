<?php

declare(strict_types=1);

namespace App\Households\Domain\ValueObject;

use App\Households\Domain\Exception\InvalidDateRange;
use DateTimeImmutable;

/**
 * Date range with a required start ("from") and an optional end ("to").
 * When "to" is null the range is open-ended. When both are present the
 * interval is inclusive on both ends — [from, to] — and "to" must be
 * greater than or equal to "from".
 */
final readonly class DateRange
{
    public DateTimeImmutable $from;
    public ?DateTimeImmutable $to;

    private function __construct(DateTimeImmutable $from, ?DateTimeImmutable $to)
    {
        $this->from = $from;
        $this->to = $to;
    }

    public static function of(DateTimeImmutable $from, ?DateTimeImmutable $to = null): self
    {
        if ($to !== null && $to < $from) {
            throw InvalidDateRange::endBeforeStart();
        }

        return new self($from, $to);
    }

    public function contains(DateTimeImmutable $when): bool
    {
        if ($when < $this->from) {
            return false;
        }

        if ($this->to !== null && $when > $this->to) {
            return false;
        }

        return true;
    }

    public function equals(self $other): bool
    {
        return $this->from == $other->from && $this->to == $other->to;
    }
}
