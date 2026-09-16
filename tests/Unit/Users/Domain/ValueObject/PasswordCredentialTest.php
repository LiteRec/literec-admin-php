<?php

declare(strict_types=1);

namespace App\Tests\Unit\Users\Domain\ValueObject;

use App\Users\Domain\ValueObject\HashedPassword;
use App\Users\Domain\ValueObject\PasswordCredential;
use App\Users\Domain\ValueObject\PasswordState;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[Small]
final class PasswordCredentialTest extends TestCase
{
    private const string SAMPLE_HASH = '$2y$10$abcdefghijklmnopqrstuuvwxyz0123456789ABCDEFGHIJKLMNOPQR';

    #[Test]
    #[TestDox('::established() builds an Established credential with no issuance instant.')]
    public function established_builds_an_established_credential(): void
    {
        $hash = HashedPassword::fromHash(self::SAMPLE_HASH);

        $credential = PasswordCredential::established($hash);

        self::assertTrue($credential->hash->equals($hash));
        self::assertSame(PasswordState::Established, $credential->state);
        self::assertNull($credential->oneTimePasswordIssuedAt);
    }

    #[Test]
    #[TestDox('::oneTimeIssued() builds an OneTimeIssued credential carrying the issuance instant.')]
    public function one_time_issued_builds_a_one_time_issued_credential(): void
    {
        $hash = HashedPassword::fromHash(self::SAMPLE_HASH);
        $issuedAt = new DateTimeImmutable('2026-01-01 12:00:00');

        $credential = PasswordCredential::oneTimeIssued($hash, $issuedAt);

        self::assertTrue($credential->hash->equals($hash));
        self::assertSame(PasswordState::OneTimeIssued, $credential->state);
        self::assertEquals($issuedAt, $credential->oneTimePasswordIssuedAt);
    }

    #[Test]
    #[TestDox('::of() builds a credential in any state, including OneTimeConsumed.')]
    public function of_builds_a_credential_in_any_state(): void
    {
        $hash = HashedPassword::fromHash(self::SAMPLE_HASH);
        $issuedAt = new DateTimeImmutable('2026-01-01 12:00:00');

        $credential = PasswordCredential::of($hash, PasswordState::OneTimeConsumed, $issuedAt);

        self::assertTrue($credential->hash->equals($hash));
        self::assertSame(PasswordState::OneTimeConsumed, $credential->state);
        self::assertEquals($issuedAt, $credential->oneTimePasswordIssuedAt);
    }

    #[Test]
    #[TestDox('::equals() compares hash, state, and issuance instant.')]
    public function equals_compares_all_components(): void
    {
        $hash = HashedPassword::fromHash(self::SAMPLE_HASH);
        $issuedAt = new DateTimeImmutable('2026-01-01 12:00:00');

        $a = PasswordCredential::oneTimeIssued($hash, $issuedAt);
        $b = PasswordCredential::oneTimeIssued($hash, $issuedAt);

        self::assertTrue($a->equals($b));
    }

    #[Test]
    #[TestDox('::equals() is false when the issuance instant differs.')]
    public function equals_is_false_when_issuance_instant_differs(): void
    {
        $hash = HashedPassword::fromHash(self::SAMPLE_HASH);

        $a = PasswordCredential::oneTimeIssued($hash, new DateTimeImmutable('2026-01-01 12:00:00'));
        $b = PasswordCredential::oneTimeIssued($hash, new DateTimeImmutable('2026-01-02 12:00:00'));

        self::assertFalse($a->equals($b));
    }

    #[Test]
    #[TestDox('::equals() is false when the state differs.')]
    public function equals_is_false_when_state_differs(): void
    {
        $hash = HashedPassword::fromHash(self::SAMPLE_HASH);

        $a = PasswordCredential::established($hash);
        $b = PasswordCredential::of($hash, PasswordState::OneTimeConsumed, null);

        self::assertFalse($a->equals($b));
    }

    #[Test]
    #[TestDox('::equals() is false when the hash differs.')]
    public function equals_is_false_when_hash_differs(): void
    {
        $other = '$2y$10$zzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzz0';

        $a = PasswordCredential::established(HashedPassword::fromHash(self::SAMPLE_HASH));
        $b = PasswordCredential::established(HashedPassword::fromHash($other));

        self::assertFalse($a->equals($b));
    }
}
