<?php

declare(strict_types=1);

namespace App\Households\Domain\ValueObject;

/**
 * Immutable, de-duplicated collection of {@see TransactionReference}
 * values selected for a split (LRA-209).
 *
 * A generic collection is not inherently required to be non-empty; the
 * "a split needs at least one transaction" business rule belongs to the
 * split operation itself ({@see \App\Households\Domain\Household::splitMember()},
 * via {@see \App\Households\Domain\Exception\SplitSelectionEmpty}), which
 * has the {@see \App\Households\Domain\ValueObject\MemberId} context that
 * exception carries. This type stays a plain collection so it is reusable
 * wherever a set of transaction references is needed.
 */
final readonly class TransactionReferences
{
    /** @var list<TransactionReference> */
    private array $items;

    private function __construct(TransactionReference ...$refs)
    {
        $unique = [];
        foreach ($refs as $ref) {
            if (!self::containsIn($unique, $ref)) {
                $unique[] = $ref;
            }
        }

        $this->items = $unique;
    }

    public static function of(TransactionReference ...$refs): self
    {
        return new self(...$refs);
    }

    /**
     * @param list<string> $values
     */
    public static function fromStrings(array $values): self
    {
        return new self(...array_map(
            static fn(string $value): TransactionReference => TransactionReference::of($value),
            $values,
        ));
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function contains(TransactionReference $ref): bool
    {
        return self::containsIn($this->items, $ref);
    }

    /**
     * @return list<string>
     */
    public function toStrings(): array
    {
        return array_map(static fn(TransactionReference $ref): string => $ref->value, $this->items);
    }

    public function equals(self $other): bool
    {
        $ours = $this->toStrings();
        $theirs = $other->toStrings();
        sort($ours);
        sort($theirs);

        return $ours === $theirs;
    }

    /**
     * @param list<TransactionReference> $haystack
     */
    private static function containsIn(array $haystack, TransactionReference $needle): bool
    {
        foreach ($haystack as $item) {
            if ($item->equals($needle)) {
                return true;
            }
        }

        return false;
    }
}
