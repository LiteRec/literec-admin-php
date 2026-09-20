<?php

declare(strict_types=1);

namespace App\Tests\Unit\Administration\Domain;

use App\Administration\Domain\Administrator;
use App\Administration\Domain\AdministratorTenure;
use App\Administration\Domain\Exception\TenureAlreadyClosed;
use App\Administration\Domain\ValueObject\Actor;
use App\Administration\Domain\ValueObject\AdministratorId;
use App\Administration\Domain\ValueObject\AdministratorTenureId;
use App\Administration\Domain\ValueObject\RankId;
use App\Administration\Domain\ValueObject\RevocationReason;
use App\Administration\Domain\ValueObject\SignInAccountId;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[Small]
final class AdministratorTenureTest extends TestCase
{
    private const string ADMINISTRATOR_ID = '019571bf-5d51-7000-b500-00000000ad01';
    private const string SIGN_IN_ACCOUNT_ID = '019571bf-5d51-7000-b500-00000000ad02';
    private const string RANK_ID = '019571bf-5d51-7000-b500-00000000ad03';
    private const string TENURE_ID = '019571bf-5d51-7000-b500-00000000ad04';

    private MockClock $clock;

    protected function setUp(): void
    {
        $this->clock = new MockClock(new DateTimeImmutable('2026-05-27 12:00:00'));
    }

    #[Test]
    #[TestDox('A newly granted tenure is open and carries its grantor and timestamp.')]
    public function a_newly_granted_tenure_is_open(): void
    {
        $administrator = $this->administrator();

        $tenure = $administrator->tenures()[0];

        self::assertTrue($tenure->isOpen());
        self::assertNull($tenure->revokedAt());
        self::assertNull($tenure->revokedBy());
        self::assertNull($tenure->revocationReason());
        self::assertEquals($this->clock->now(), $tenure->grantedAt());
    }

    #[Test]
    #[TestDox('close() closes the tenure with the actor, timestamp, and reason.')]
    public function close_closes_the_tenure(): void
    {
        $tenure = new AdministratorTenure(
            AdministratorTenureId::fromString(self::TENURE_ID),
            $this->administrator(),
            Actor::system(),
            $this->clock->now(),
        );
        $actor = Actor::system();
        $reason = RevocationReason::of('Left the organization.');
        $revokedAt = new DateTimeImmutable('2026-06-01 09:00:00');

        $tenure->close($reason, $actor, $revokedAt);

        self::assertFalse($tenure->isOpen());
        self::assertSame($revokedAt, $tenure->revokedAt());
        self::assertTrue($tenure->revokedBy()?->equals($actor));
        self::assertTrue($tenure->revocationReason()?->equals($reason));
    }

    #[Test]
    #[TestDox('close() throws TenureAlreadyClosed when the tenure is already closed.')]
    public function close_throws_when_already_closed(): void
    {
        $tenure = new AdministratorTenure(
            AdministratorTenureId::fromString(self::TENURE_ID),
            $this->administrator(),
            Actor::system(),
            $this->clock->now(),
        );
        $tenure->close(RevocationReason::of('First reason.'), Actor::system(), $this->clock->now());

        $this->expectException(TenureAlreadyClosed::class);

        $tenure->close(RevocationReason::of('Second reason.'), Actor::system(), $this->clock->now());
    }

    private function administrator(): Administrator
    {
        return Administrator::grant(
            AdministratorId::fromString(self::ADMINISTRATOR_ID),
            SignInAccountId::fromString(self::SIGN_IN_ACCOUNT_ID),
            RankId::fromString(self::RANK_ID),
            AdministratorTenureId::fromString(self::TENURE_ID),
            Actor::system(),
            $this->clock,
        );
    }
}
