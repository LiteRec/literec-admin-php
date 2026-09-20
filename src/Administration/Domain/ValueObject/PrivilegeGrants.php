<?php

declare(strict_types=1);

namespace App\Administration\Domain\ValueObject;

use App\Administration\Domain\Privilege;

/**
 * Immutable collection of every {@see PrivilegeGrant} contributed by every
 * {@see \App\Administration\Domain\PrivilegeGrantSource} for one
 * administrator (LRA-270).
 *
 * Unlike {@see PrivilegeSet}, this collection is deliberately NOT
 * de-duplicated by privilege: {@see self::merge()} is a set union on the
 * privilege for the purpose of {@see self::privileges()}, but every
 * contributing grant is retained and reachable via {@see self::all()}.
 * When two sources grant the same privilege, {@see self::originOf()}
 * reports only the first (the "primary" grant, recorded as this
 * privilege's origin), while {@see self::all()} still surfaces the rest.
 * Retaining the non-primary grants is a consumed contract, not an
 * incidental detail: LRA-273 projects the primary grant into indexed
 * columns and the full set into a JSONB column for its access review,
 * and narrowing this collection to a single winning grant per privilege
 * would silently degrade that review without breaking any other caller.
 */
final readonly class PrivilegeGrants
{
    /** @var list<PrivilegeGrant> */
    private array $items;

    private function __construct(PrivilegeGrant ...$grants)
    {
        $this->items = array_values($grants);
    }

    public static function of(PrivilegeGrant ...$grants): self
    {
        return new self(...$grants);
    }

    public static function none(): self
    {
        return new self();
    }

    /**
     * A set union on the contributing grants: every grant from both
     * collections is retained (see this class's docblock on why),
     * preserving $this's grants ahead of $other's so an earlier source's
     * grant remains the recorded origin for a privilege both contribute.
     */
    public function merge(self $other): self
    {
        return new self(...$this->items, ...$other->items);
    }

    /**
     * The de-duplicated set of privileges any contributing grant names —
     * the input {@see \App\Administration\Infrastructure\Security\PrivilegeVoter}
     * and {@see \App\Administration\Application\Query\GetGrantedPrivilegesHandler}
     * both check against.
     */
    public function privileges(): PrivilegeSet
    {
        return PrivilegeSet::of(...array_map(
            static fn (PrivilegeGrant $grant): Privilege => $grant->privilege,
            $this->items,
        ));
    }

    /**
     * The first grant contributing $privilege, or null when nothing in
     * this collection grants it. "First" is the recorded origin for this
     * privilege — see this class's docblock.
     */
    public function originOf(Privilege $privilege): ?PrivilegeGrant
    {
        foreach ($this->items as $grant) {
            if ($grant->privilege === $privilege) {
                return $grant;
            }
        }

        return null;
    }

    /**
     * Every contributing grant, primary and non-primary alike — the full
     * set LRA-273's access review reads. See this class's docblock.
     *
     * @return list<PrivilegeGrant>
     */
    public function all(): array
    {
        return $this->items;
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function equals(self $other): bool
    {
        if (count($this->items) !== count($other->items)) {
            return false;
        }

        $ours = array_map(self::grantSignature(...), $this->items);
        $theirs = array_map(self::grantSignature(...), $other->items);
        sort($ours);
        sort($theirs);

        return $ours === $theirs;
    }

    private static function grantSignature(PrivilegeGrant $grant): string
    {
        return implode('|', [
            $grant->privilege->value,
            $grant->origin->value,
            $grant->sourceId,
            $grant->sourceName,
        ]);
    }
}
