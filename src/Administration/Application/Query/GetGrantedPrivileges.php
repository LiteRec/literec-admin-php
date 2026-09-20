<?php

declare(strict_types=1);

namespace App\Administration\Application\Query;

/**
 * Parameterless query DTO: "which privileges does the currently
 * authenticated sign-in account hold". The UI dispatches this to decide
 * what to render — never what to allow, which stays
 * {@see \App\Administration\Infrastructure\Security\PrivilegeVoter}'s job
 * alone. Both resolve through the same {@see \App\Administration\Domain\EffectivePrivileges}
 * port, so there is one source of truth behind the two decisions.
 */
final readonly class GetGrantedPrivileges
{
}
