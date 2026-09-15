<?php

declare(strict_types=1);

namespace App\Users\Infrastructure\Security;

use App\Users\Domain\OneTimePasswordGenerator;
use App\Users\Domain\ValueObject\OneTimePassword;
use Symfony\Component\String\ByteString;

/**
 * Generates a random one-time password using {@see ByteString}'s default
 * alphanumeric-minus-ambiguous alphabet (omits 0/O/l/I), which matters when
 * a staff member reads the credential out loud over a service counter.
 */
final class Base58OneTimePasswordGenerator implements OneTimePasswordGenerator
{
    public function generate(): OneTimePassword
    {
        return OneTimePassword::of(ByteString::fromRandom(OneTimePassword::LENGTH)->toString());
    }
}
