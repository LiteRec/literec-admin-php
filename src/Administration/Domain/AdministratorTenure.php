<?php

declare(strict_types=1);

namespace App\Administration\Domain;

use App\Administration\Domain\Exception\TenureAlreadyClosed;
use App\Administration\Domain\ValueObject\Actor;
use App\Administration\Domain\ValueObject\AdministratorTenureId;
use App\Administration\Domain\ValueObject\RevocationReason;
use DateTimeImmutable;

/**
 * Child entity owned by {@see Administrator}, recording one continuous
 * period the administrator held their record. Modelled as a list of
 * tenures rather than four nullable columns on the aggregate itself: a
 * revoke() closes the current tenure without erasing it, and a later
 * regrant() opens a new one, so "when did they leave, and when did they
 * come back" stays answerable from history rather than being overwritten
 * (AC — no legacy data is migrated, but the legacy `admin_flag`/`rank`
 * columns this replaces carried no such history at all).
 *
 * Although the constructor and accessors are technically `public` (PHP has
 * no package-private modifier), this is considered internal to the
 * aggregate: callers in Application or Infrastructure layers must go
 * through {@see Administrator} methods. Direct instantiation outside the
 * aggregate is a programming error. Same treatment as
 * {@see \App\Households\Domain\HouseholdAffiliation}.
 */
final class AdministratorTenure
{
    private AdministratorTenureId $id;
    private Administrator $administrator;
    private DateTimeImmutable $grantedAt;
    private Actor $grantedBy;
    private ?DateTimeImmutable $revokedAt = null;
    private ?Actor $revokedBy = null;
    private ?RevocationReason $revocationReason = null;

    /**
     * Internal-to-aggregate constructor. Use {@see Administrator::grant()}
     * or {@see Administrator::regrant()} to create instances.
     */
    public function __construct(
        AdministratorTenureId $id,
        Administrator $administrator,
        Actor $grantedBy,
        DateTimeImmutable $grantedAt,
    ) {
        $this->id = $id;
        $this->administrator = $administrator;
        $this->grantedBy = $grantedBy;
        $this->grantedAt = $grantedAt;
    }

    public function id(): AdministratorTenureId
    {
        return $this->id;
    }

    public function grantedAt(): DateTimeImmutable
    {
        return $this->grantedAt;
    }

    public function grantedBy(): Actor
    {
        return $this->grantedBy;
    }

    public function revokedAt(): ?DateTimeImmutable
    {
        return $this->revokedAt;
    }

    public function revokedBy(): ?Actor
    {
        return $this->revokedBy;
    }

    public function revocationReason(): ?RevocationReason
    {
        return $this->revocationReason;
    }

    public function isOpen(): bool
    {
        return $this->revokedAt === null;
    }

    /**
     * @throws TenureAlreadyClosed when this tenure has already been closed.
     */
    public function close(RevocationReason $reason, Actor $actor, DateTimeImmutable $at): void
    {
        if (!$this->isOpen()) {
            throw TenureAlreadyClosed::for($this->id);
        }

        $this->revokedAt = $at;
        $this->revokedBy = $actor;
        $this->revocationReason = $reason;
    }

    /**
     * Wired as a Doctrine postLoad lifecycle callback via the XML
     * mapping (LRA-269 review round). Doctrine ORM 3.7 does not leave
     * $revokedBy null when every one of its embedded columns (kind,
     * administrator_id, sign_in_account_id) is NULL — it constructs an
     * {@see Actor} with every field null instead, confirmed against a
     * live reload. That object would fail every one of Actor's own
     * invariants (see its docblock) and would make an open tenure
     * report a non-null revokedBy(). This collapses that hydration
     * artefact back to a genuine null immediately after load, so the
     * rest of the domain never has to know Doctrine produced it.
     */
    public function normalizeAfterLoad(): void
    {
        if ($this->revokedBy?->kind === null) {
            $this->revokedBy = null;
        }
    }
}
