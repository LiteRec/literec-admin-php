<?php

declare(strict_types=1);

namespace App\Tests\Unit\Users\Infrastructure\Identity;

use App\Users\Infrastructure\Identity\Uuid7IdentityGenerator;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[Small]
final class Uuid7IdentityGeneratorTest extends TestCase
{
    #[Test]
    #[TestDox('Generates a UUID v7 in RFC 4122 canonical form.')]
    public function generates_a_valid_uuid_v7_in_rfc_4122_form(): void
    {
        $clock = new MockClock(new DateTimeImmutable('2026-01-01 12:00:00'));

        $id = (new Uuid7IdentityGenerator($clock))->nextUserId();

        // RFC 4122 canonical form: 8-4-4-4-12 lowercase hex with version
        // nibble 7 and variant high bits 10xx (8/9/a/b).
        self::assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $id,
        );
    }

    #[Test]
    #[TestDox('Returns lexicographically sortable identifiers in creation order when the clock advances.')]
    public function returns_lexicographically_sortable_ids_when_clock_advances(): void
    {
        $clock = new MockClock(new DateTimeImmutable('2026-01-01 12:00:00'));
        $generator = new Uuid7IdentityGenerator($clock);

        $ids = [];
        for ($i = 0; $i < 8; $i++) {
            $ids[] = $generator->nextUserId();
            // 1 ms apart guarantees the UUID v7 timestamp prefix advances.
            $clock->sleep(0.001);
        }

        $sorted = $ids;
        sort($sorted);

        self::assertSame($ids, $sorted);
    }

    #[Test]
    #[TestDox('Returns unique identifiers across many calls at the same instant.')]
    public function returns_unique_ids_across_many_calls(): void
    {
        $clock = new MockClock(new DateTimeImmutable('2026-01-01 12:00:00'));
        $generator = new Uuid7IdentityGenerator($clock);

        $ids = [];
        for ($i = 0; $i < 1000; $i++) {
            $ids[] = $generator->nextUserId();
        }

        // Even with a frozen clock, UUID v7's random tail field
        // guarantees collision-resistance across 1000 calls.
        self::assertCount(1000, array_unique($ids));
    }
}
