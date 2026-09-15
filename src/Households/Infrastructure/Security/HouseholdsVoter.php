<?php

declare(strict_types=1);

namespace App\Households\Infrastructure\Security;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Page-level voter for the Households section (LRA-212), mirroring
 * {@see \App\Inventory\Infrastructure\Security\InventoryVoter}. No other
 * Households endpoint carries a voter today (the firewall is ROLE_USER
 * only — see {@see \App\Households\Infrastructure\Http\Controller\MemberLifecycleController}'s
 * docblock); anonymization is gated here anyway because it is the one
 * irreversible, PII-destroying action in the context, and the acceptance
 * criteria call for a server-enforced gate distinct from the confirmation
 * dialog itself.
 *
 * Grants to any authenticated user today, matching every other voter in
 * the codebase; tightening to a specific staff role is a deliberate
 * follow-up once the staff role model is settled.
 *
 * @extends Voter<string, mixed>
 */
final class HouseholdsVoter extends Voter
{
    public const string ANONYMIZE_MEMBER = 'anonymize_member';

    /** @var list<string> */
    private const array SUPPORTED = [
        self::ANONYMIZE_MEMBER,
    ];

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, self::SUPPORTED, true);
    }

    protected function voteOnAttribute(
        string $attribute,
        mixed $subject,
        TokenInterface $token,
        ?Vote $vote = null,
    ): bool {
        return $token->getUser() !== null;
    }
}
