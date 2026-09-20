<?php

declare(strict_types=1);

namespace App\Administration\Infrastructure\Security;

use App\Administration\Application\Security\CurrentAdministrator;
use App\Administration\Domain\EffectivePrivileges;
use App\Administration\Domain\Privilege;
use App\Administration\Domain\ValueObject\AdministratorId;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\CacheableVoterInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

/**
 * The single decision point for every privilege check in the application
 * (LRA-270). Implements {@see CacheableVoterInterface} directly rather
 * than extending Symfony's {@see \Symfony\Component\Security\Core\Authorization\Voter\Voter}
 * base class: the project's composition-over-inheritance rule, and the
 * fact that `Voter::supportsAttribute()` returns true unconditionally,
 * which would defeat the caching {@see CacheableVoterInterface} exists
 * to enable.
 *
 * Obtains the administrator identity by asking {@see CurrentAdministrator},
 * never the Request, and never re-derives it itself — there is one
 * resolution path from principal to administrator and LRA-279 owns it.
 * Every decision resolves through {@see EffectivePrivileges}, the same
 * port {@see \App\Administration\Application\Query\GetGrantedPrivilegesHandler}
 * uses for the read path — one source of truth, two decisions.
 *
 * `supportsAttribute()` returns true both for a catalogue
 * {@see Privilege} name and for a string merely SHAPED like one
 * (uppercase snake case), so a typo'd or since-removed privilege name is
 * denied loudly by {@see self::voteOnPrivilege()} rather than silently
 * abstained on — abstaining would let a misconfigured
 * `#[IsGranted]` attribute fall through to another voter or to the
 * access decision manager's abstain policy instead of failing the check
 * it was clearly meant to gate. Symfony's own reserved attribute
 * vocabulary (`ROLE_*`, `PUBLIC_ACCESS`, `IS_AUTHENTICATED*`,
 * `IS_IMPERSONATOR`) is carved out of that shape match: those attributes
 * are checked on literally every request via `access_control`/`ROLE_USER`
 * and are never privilege names, so matching them would resolve this
 * voter's full privilege pipeline on every single request for no
 * decision that matters — the {@see \Symfony\Component\Security\Core\Authorization\Voter\Voter}
 * built-ins that actually own those attributes already grant or deny
 * them, and this voter's `affirmative`-strategy vote would be a no-op
 * either way (see config/packages/security.yaml), just wasted work.
 */
final class PrivilegeVoter implements VoterInterface, CacheableVoterInterface
{
    private const string PRIVILEGE_NAME_SHAPE = '/^[A-Z][A-Z0-9_]*$/';

    private const string RESERVED_ATTRIBUTE_PREFIX = 'ROLE_';

    /** @var list<string> */
    private const array RESERVED_ATTRIBUTES = [
        'PUBLIC_ACCESS',
        'IS_AUTHENTICATED',
        'IS_AUTHENTICATED_ANONYMOUSLY',
        'IS_AUTHENTICATED_REMEMBERED',
        'IS_AUTHENTICATED_FULLY',
        'IS_IMPERSONATOR',
    ];

    public function __construct(
        private readonly CurrentAdministrator $currentAdministrator,
        private readonly EffectivePrivileges $effectivePrivileges,
    ) {
    }

    public function supportsAttribute(string $attribute): bool
    {
        if (Privilege::tryFrom($attribute) !== null) {
            return true;
        }

        if (
            str_starts_with($attribute, self::RESERVED_ATTRIBUTE_PREFIX)
            || in_array($attribute, self::RESERVED_ATTRIBUTES, true)
        ) {
            return false;
        }

        return preg_match(self::PRIVILEGE_NAME_SHAPE, $attribute) === 1;
    }

    /**
     * Subjects are not consulted in this slice — subject-scoped checks
     * are slice (c)'s (LRA-276) business, via the closure form of
     * `#[IsGranted]`.
     */
    public function supportsType(string $subjectType): bool
    {
        return true;
    }

    public function vote(TokenInterface $token, mixed $subject, array $attributes, ?Vote $vote = null): int
    {
        $result = self::ACCESS_ABSTAIN;

        foreach ($attributes as $attribute) {
            if (!is_string($attribute) || !$this->supportsAttribute($attribute)) {
                continue;
            }

            if ($this->voteOnPrivilege($attribute, $vote) === self::ACCESS_DENIED) {
                return self::ACCESS_DENIED;
            }

            $result = self::ACCESS_GRANTED;
        }

        return $result;
    }

    private function voteOnPrivilege(string $attribute, ?Vote $vote): int
    {
        $privilege = Privilege::tryFrom($attribute);

        if ($privilege === null) {
            $vote?->addReason(sprintf('"%s" does not name a privilege in the catalogue.', $attribute));

            return self::ACCESS_DENIED;
        }

        $standing = $this->currentAdministrator->standing();

        if ($standing === null || !$standing->isActive()) {
            $vote?->addReason(sprintf('Not currently staff; missing %s.', $privilege->value));

            return self::ACCESS_DENIED;
        }

        $grants = $this->effectivePrivileges->forAdministrator(AdministratorId::fromString($standing->administratorId));
        $grant = $grants->originOf($privilege);

        if ($grant === null) {
            $vote?->addReason(sprintf('Missing %s.', $privilege->value));

            return self::ACCESS_DENIED;
        }

        $vote?->addReason(sprintf(
            'Granted %s via %s "%s".',
            $privilege->value,
            $grant->origin->value,
            $grant->sourceName,
        ));

        return self::ACCESS_GRANTED;
    }
}
