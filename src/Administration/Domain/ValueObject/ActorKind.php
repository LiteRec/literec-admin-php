<?php

declare(strict_types=1);

namespace App\Administration\Domain\ValueObject;

/**
 * The three kinds of {@see Actor} that can perform an Administration
 * write. Backing values are the primitive form a command DTO carries
 * (CLAUDE.md: command/query DTOs are primitive-only) so a handler can
 * reconstruct the matching Actor case without depending on the enum
 * type at the DTO boundary.
 *
 * Created by this slice under the context's merge-first rule: LRA-269
 * specifies this type but merges after LRA-268.
 */
enum ActorKind: string
{
    case Administrator = 'ADMINISTRATOR';
    case SignInAccount = 'SIGN_IN_ACCOUNT';
    case System = 'SYSTEM';
}
