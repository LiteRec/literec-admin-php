<?php

declare(strict_types=1);

namespace App\Administration\Infrastructure\Identity;

use App\Administration\Domain\IdentityGenerator;
use App\Administration\Domain\ValueObject\AdministratorId;
use App\Administration\Domain\ValueObject\AdministratorTenureId;
use App\Administration\Domain\ValueObject\RankId;
use App\Administration\Domain\ValueObject\RoleId;
use Psr\Clock\ClockInterface;
use Symfony\Component\Uid\UuidV7;

final class Uuid7IdentityGenerator implements IdentityGenerator
{
    public function __construct(private readonly ClockInterface $clock)
    {
    }

    public function nextRoleId(): RoleId
    {
        return RoleId::fromString(UuidV7::generate($this->clock->now()));
    }

    public function nextRankId(): RankId
    {
        return RankId::fromString(UuidV7::generate($this->clock->now()));
    }

    public function nextAdministratorId(): AdministratorId
    {
        return AdministratorId::fromString(UuidV7::generate($this->clock->now()));
    }

    public function nextTenureId(): AdministratorTenureId
    {
        return AdministratorTenureId::fromString(UuidV7::generate($this->clock->now()));
    }
}
