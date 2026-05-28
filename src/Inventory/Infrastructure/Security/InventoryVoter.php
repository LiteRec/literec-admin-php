<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Security;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Page-level voter for the Inventory section (LRA-85).
 *
 * Gates the action-bar buttons on the Inventory list page. Subjects are
 * ignored because the checks are coarse (view / manage / receive stock /
 * manage purchase orders) — any authenticated staff user is currently
 * granted; finer role-based filtering arrives in later tickets.
 *
 * @extends Voter<string, mixed>
 */
final class InventoryVoter extends Voter
{
    public const string VIEW = 'view_inventory';

    public const string MANAGE = 'manage_inventory';

    public const string RECEIVE = 'receive_stock';

    public const string TAKE = 'take_inventory';

    public const string MANAGE_PURCHASE_ORDERS = 'manage_purchase_orders';

    /** @var list<string> */
    private const array SUPPORTED = [
        self::VIEW,
        self::MANAGE,
        self::RECEIVE,
        self::TAKE,
        self::MANAGE_PURCHASE_ORDERS,
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
