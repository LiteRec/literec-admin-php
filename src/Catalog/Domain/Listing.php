<?php

declare(strict_types=1);

namespace App\Catalog\Domain;

use App\Catalog\Domain\Event\ListingArchived;
use App\Catalog\Domain\Event\ListingFeesUpdated;
use App\Catalog\Domain\Event\ListingLedgerAccountUpdated;
use App\Catalog\Domain\Event\ListingRegistered;
use App\Catalog\Domain\Event\ListingRenamed;
use App\Catalog\Domain\Event\ListingTaxTreatmentUpdated;
use App\Catalog\Domain\Exception\InvalidListingName;
use App\Catalog\Domain\Exception\ListingIsArchived;
use App\Catalog\Domain\ValueObject\Fee;
use App\Catalog\Domain\ValueObject\LedgerAccount;
use App\Catalog\Domain\ValueObject\ListingCode;
use App\Catalog\Domain\ValueObject\ListingId;
use App\Catalog\Domain\ValueObject\TaxTreatment;
use DateTimeImmutable;
use Psr\Clock\ClockInterface;

/**
 * Catalog Listing aggregate.
 *
 * Pure domain class: no Symfony or Doctrine imports. State changes flow
 * through intention-revealing methods that buffer domain events; the
 * application service releases them after the persistence transaction
 * commits.
 *
 * The aggregate stores fees as a plain PHP list of {@see Fee} value
 * objects rather than a Doctrine Collection — there are no child
 * entities, only nested value objects mapped via a JSON column at the
 * infrastructure layer.
 */
final class Listing
{
    use AggregateRoot;

    private ListingId $id;

    private ListingCode $code;

    private ListingKind $kind;

    private string $name;

    /** @var list<Fee> */
    private array $fees;

    private TaxTreatment $taxTreatment;

    private LedgerAccount $ledgerAccount;

    private bool $archived;

    private DateTimeImmutable $registeredAt;

    private DateTimeImmutable $updatedAt;

    private function __construct()
    {
    }

    /**
     * @param list<Fee> $fees
     */
    public static function register(
        ListingId $id,
        ListingCode $code,
        ListingKind $kind,
        string $name,
        array $fees,
        TaxTreatment $taxTreatment,
        LedgerAccount $ledgerAccount,
        ClockInterface $clock,
    ): self {
        $listing = new self();
        $listing->id = $id;
        $listing->code = $code;
        $listing->kind = $kind;
        $listing->name = self::validateName($name);
        $listing->fees = $fees;
        $listing->taxTreatment = $taxTreatment;
        $listing->ledgerAccount = $ledgerAccount;
        $listing->archived = false;
        $listing->registeredAt = $clock->now();
        $listing->updatedAt = $listing->registeredAt;

        $listing->recordThat(new ListingRegistered(
            $id,
            $code,
            $kind,
            $listing->name,
            $listing->fees,
            $taxTreatment,
            $ledgerAccount,
            $listing->registeredAt,
        ));

        return $listing;
    }

    public function id(): ListingId
    {
        return $this->id;
    }

    public function code(): ListingCode
    {
        return $this->code;
    }

    public function kind(): ListingKind
    {
        return $this->kind;
    }

    public function name(): string
    {
        return $this->name;
    }

    /**
     * @return list<Fee>
     */
    public function fees(): array
    {
        return $this->fees;
    }

    public function taxTreatment(): TaxTreatment
    {
        return $this->taxTreatment;
    }

    public function ledgerAccount(): LedgerAccount
    {
        return $this->ledgerAccount;
    }

    public function isArchived(): bool
    {
        return $this->archived;
    }

    public function registeredAt(): DateTimeImmutable
    {
        return $this->registeredAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * @param list<Fee> $fees
     */
    public function updateFees(array $fees, ClockInterface $clock): void
    {
        $this->guardNotArchived();

        if (self::feesEqual($this->fees, $fees)) {
            return;
        }

        $this->fees = $fees;
        $this->updatedAt = $clock->now();
        $this->recordThat(new ListingFeesUpdated($this->id, $fees, $this->updatedAt));
    }

    public function updateTaxTreatment(TaxTreatment $taxTreatment, ClockInterface $clock): void
    {
        $this->guardNotArchived();

        if ($this->taxTreatment->equals($taxTreatment)) {
            return;
        }

        $this->taxTreatment = $taxTreatment;
        $this->updatedAt = $clock->now();
        $this->recordThat(new ListingTaxTreatmentUpdated($this->id, $taxTreatment, $this->updatedAt));
    }

    public function updateLedgerAccount(LedgerAccount $ledgerAccount, ClockInterface $clock): void
    {
        $this->guardNotArchived();

        if ($this->ledgerAccount->equals($ledgerAccount)) {
            return;
        }

        $this->ledgerAccount = $ledgerAccount;
        $this->updatedAt = $clock->now();
        $this->recordThat(new ListingLedgerAccountUpdated($this->id, $ledgerAccount, $this->updatedAt));
    }

    public function rename(string $name, ClockInterface $clock): void
    {
        $this->guardNotArchived();

        $next = self::validateName($name);

        if ($next === $this->name) {
            return;
        }

        $this->name = $next;
        $this->updatedAt = $clock->now();
        $this->recordThat(new ListingRenamed($this->id, $next, $this->updatedAt));
    }

    public function archive(ClockInterface $clock): void
    {
        if ($this->archived) {
            return;
        }

        $this->archived = true;
        $this->updatedAt = $clock->now();
        $this->recordThat(new ListingArchived($this->id, $this->updatedAt));
    }

    private function guardNotArchived(): void
    {
        if ($this->archived) {
            throw ListingIsArchived::for($this->id);
        }
    }

    private static function validateName(string $name): string
    {
        $trimmed = trim($name);

        if ($trimmed === '') {
            throw InvalidListingName::empty();
        }

        $length = mb_strlen($trimmed, 'UTF-8');

        if ($length > InvalidListingName::MAX_LENGTH) {
            throw InvalidListingName::tooLong($length);
        }

        return $trimmed;
    }

    /**
     * @param list<Fee> $a
     * @param list<Fee> $b
     */
    private static function feesEqual(array $a, array $b): bool
    {
        if (count($a) !== count($b)) {
            return false;
        }

        foreach ($a as $index => $fee) {
            if (! $fee->equals($b[$index])) {
                return false;
            }
        }

        return true;
    }
}
