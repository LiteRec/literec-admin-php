<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Fixtures;

use DateTimeImmutable;
use Psr\Clock\ClockInterface;

/**
 * Produces deterministic UUID v7 strings from a seed + monotonic
 * counter, so repeated fixture loads at the same seed yield the same
 * identifiers byte-for-byte.
 *
 * Layout (RFC 9562):
 *   - Bits  0..47 : unix-millisecond timestamp pulled from the injected
 *                   clock. With {@see FixedClock} this is constant for
 *                   the whole load, which is the property we want.
 *   - Bits 48..51 : version nibble, fixed to 0b0111 (7).
 *   - Bits 52..63 : rand_a — high 12 bits of the per-instance counter.
 *   - Bits 64..65 : variant, fixed to 0b10.
 *   - Bits 66..127: rand_b — derived from the seed + counter via
 *                   SHA-256 so distinct seeds produce distinct ID
 *                   streams. The counter is woven through both rand_a
 *                   and rand_b so the resulting strings stay
 *                   monotonically ordered (UUID v7's intent) within
 *                   a single load.
 *
 * Reusing the same seed twice produces the same sequence of UUIDs.
 * Use only for fixture/test code; production must use the real
 * {@see \App\Inventory\Infrastructure\Identity\Uuid7IdentityGenerator}.
 */
final class DeterministicUuid7Sequencer
{
    private readonly string $entropyKey;
    private int $counter;

    public function __construct(
        private readonly ClockInterface $clock,
        int $seed,
    ) {
        $this->entropyKey = hash('sha256', sprintf('lra-92-fixture-seed::%d', $seed), true);
        $this->counter = 0;
    }

    /**
     * Returns the next deterministic UUID v7 string.
     */
    public function next(): string
    {
        $this->counter++;

        $now = $this->clock->now();
        $unixMs = self::unixMs($now);
        $counter = $this->counter;

        // Derive an 8-byte tail from HMAC-SHA256(counter, seed-entropy).
        // Hash output is 32 bytes; we take 8 to fill the rand_b region
        // (62 bits used after the variant is masked in).
        $tailHash = hash_hmac('sha256', pack('J', $counter), $this->entropyKey, true);

        // ---- bytes 0..5 : unix-millisecond timestamp (big-endian, 48 bits) ----
        $timestampBytes = self::packUnixMs($unixMs);

        // ---- bytes 6..7 : version (high nibble = 0x7) + 12-bit rand_a ----
        // High nibble of byte 6 is the version (0x7); low nibble +
        // entire byte 7 carry rand_a. Use the counter directly here so
        // adjacent IDs sort lexicographically by counter.
        $randA = $counter & 0x0FFF; // 12 bits
        $byte6 = 0x70 | (($randA >> 8) & 0x0F);
        $byte7 = $randA & 0xFF;

        // ---- bytes 8..15 : variant (top 2 bits of byte 8 = 0b10) + 62-bit rand_b ----
        // Take the first 8 bytes of the HMAC tail, force the variant bits.
        $randBPrefix = substr($tailHash, 0, 8);
        $byte8 = (ord($randBPrefix[0]) & 0x3F) | 0x80;
        $randBTail = substr($randBPrefix, 1, 7);

        $bytes = $timestampBytes
            . chr($byte6)
            . chr($byte7)
            . chr($byte8)
            . $randBTail;

        return self::formatUuid($bytes);
    }

    private static function unixMs(DateTimeImmutable $when): int
    {
        // (U.u) format yields "<unix-seconds>.<microseconds>".
        $seconds = (int) $when->format('U');
        $micros = (int) $when->format('u');

        return $seconds * 1000 + intdiv($micros, 1000);
    }

    /**
     * Big-endian 48-bit packing of a unix-ms timestamp into 6 bytes.
     */
    private static function packUnixMs(int $unixMs): string
    {
        $bytes = '';
        for ($shift = 40; $shift >= 0; $shift -= 8) {
            $bytes .= chr(($unixMs >> $shift) & 0xFF);
        }

        return $bytes;
    }

    /**
     * Format 16 raw bytes as the canonical 8-4-4-4-12 RFC 9562 string.
     */
    private static function formatUuid(string $bytes): string
    {
        $hex = bin2hex($bytes);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12),
        );
    }
}
