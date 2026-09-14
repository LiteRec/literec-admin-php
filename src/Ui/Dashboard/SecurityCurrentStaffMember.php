<?php

declare(strict_types=1);

namespace App\Ui\Dashboard;

use Symfony\Bundle\SecurityBundle\Security;

/**
 * Adapts Symfony Security's token storage to CurrentStaffMember. The User
 * aggregate carries only a Username (LRA-189: no first/last name field
 * exists yet), so the first "word" of the username — split on whitespace and
 * the common username separators — stands in for a display first name.
 */
final readonly class SecurityCurrentStaffMember implements CurrentStaffMember
{
    private const string FALLBACK_NAME = 'there';

    public function __construct(private Security $security)
    {
    }

    public function firstName(): string
    {
        $identifier = $this->security->getUser()?->getUserIdentifier();
        $identifier = $identifier !== null ? trim($identifier) : '';

        if ($identifier === '') {
            return self::FALLBACK_NAME;
        }

        $words = preg_split('/[\s._-]+/u', $identifier, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $first = $words[0] ?? $identifier;

        return mb_convert_case($first, MB_CASE_TITLE);
    }
}
