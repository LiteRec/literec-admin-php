<?php

declare(strict_types=1);

namespace App\Administration\Infrastructure\Authorization;

use App\Administration\Domain\Exception\UnknownPrivilege;
use App\Administration\Domain\Privilege;
use App\Administration\Domain\PrivilegeLookup;
use Psr\Log\LoggerInterface;

/**
 * Production {@see PrivilegeLookup} adapter, backed directly by the
 * {@see Privilege} enum.
 *
 * A miss is logged here, at warning level, rather than in Domain code, so
 * Domain stays framework-free and this adapter remains the single seam
 * where an unrecognised privilege name is observed. The logger is a
 * required constructor argument with no null-object default: a silently
 * swallowed unknown-privilege check is exactly the failure mode this
 * ticket exists to remove.
 *
 * LRA-273 replaces this adapter's logging with an audit-log write and
 * removes the PSR-3 call in the same commit — the port, its contract,
 * and this one seam stay exactly as defined here, so there is never a
 * second privilege-check code path.
 */
final class CataloguePrivilegeLookup implements PrivilegeLookup
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    public function privilegeNamed(string $name): Privilege
    {
        $privilege = Privilege::tryFrom($name);

        if ($privilege === null) {
            $this->logger->warning('Rejected unknown privilege name.', ['privilege_name' => $name]);

            throw UnknownPrivilege::named($name);
        }

        return $privilege;
    }
}
