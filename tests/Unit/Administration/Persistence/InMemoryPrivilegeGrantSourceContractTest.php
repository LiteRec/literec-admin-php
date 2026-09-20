<?php

declare(strict_types=1);

namespace App\Tests\Unit\Administration\Persistence;

use App\Administration\Domain\Privilege;
use App\Administration\Domain\PrivilegeGrantSource;
use App\Administration\Domain\ValueObject\AdministratorId;
use App\Administration\Domain\ValueObject\GrantOrigin;
use App\Administration\Infrastructure\Persistence\InMemory\InMemoryPrivilegeGrantSource;
use App\Tests\Support\Trait\PrivilegeGrantSourceContractCases;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[Small]
final class InMemoryPrivilegeGrantSourceContractTest extends TestCase
{
    use PrivilegeGrantSourceContractCases;

    private const string ADMINISTRATOR_A = '019571bf-5d51-7000-b500-00000000ae01';

    private InMemoryPrivilegeGrantSource $source;

    protected function setUp(): void
    {
        $this->source = new InMemoryPrivilegeGrantSource();
    }

    protected function source(): PrivilegeGrantSource
    {
        return $this->source;
    }

    protected function administratorId(): AdministratorId
    {
        return AdministratorId::fromString(self::ADMINISTRATOR_A);
    }

    protected function expectedOrigin(): GrantOrigin
    {
        return GrantOrigin::RankRole;
    }

    protected function grantPrivilege(Privilege $privilege, string $sourceId, string $sourceName): void
    {
        $this->source->grant($this->administratorId(), $privilege, $this->expectedOrigin(), $sourceId, $sourceName);
    }
}
