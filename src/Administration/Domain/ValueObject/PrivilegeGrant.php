<?php

declare(strict_types=1);

namespace App\Administration\Domain\ValueObject;

use App\Administration\Domain\Privilege;

/**
 * One privilege together with its provenance: which {@see Privilege} was
 * granted, which kind of thing ({@see GrantOrigin}) supplied it, and the
 * identity/display name of that specific thing.
 *
 * $sourceId/$sourceName are primitive rather than a typed identity value
 * object because what supplies a grant varies by {@see GrantOrigin} — a
 * {@see \App\Administration\Domain\ValueObject\RoleId} today, a support
 * grant identity once LRA-271 ships — and this type must carry either
 * without depending on either context's identity type. This is what lets
 * the voter explain not just that access was allowed or denied, but why:
 * {@see \App\Administration\Infrastructure\Security\PrivilegeVoter} puts
 * this provenance into the security {@see \Symfony\Component\Security\Core\Authorization\Voter\Vote}'s
 * reasons on both outcomes.
 */
final readonly class PrivilegeGrant
{
    public function __construct(
        public Privilege $privilege,
        public GrantOrigin $origin,
        public string $sourceId,
        public string $sourceName,
    ) {
    }

    public function equals(self $other): bool
    {
        return $this->privilege === $other->privilege
            && $this->origin === $other->origin
            && $this->sourceId === $other->sourceId
            && $this->sourceName === $other->sourceName;
    }
}
