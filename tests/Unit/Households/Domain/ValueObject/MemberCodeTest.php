<?php

declare(strict_types=1);

namespace App\Tests\Unit\Households\Domain\ValueObject;

use App\Households\Domain\Exception\InvalidMemberCode;
use App\Households\Domain\ValueObject\MemberCode;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[Small]
final class MemberCodeTest extends TestCase
{
    #[Test]
    #[TestWith(['M000001'])]
    #[TestWith(['ABC-123'])]
    #[TestWith(['code_42'])]
    #[TestDox('Accepts an allowed code containing alphanumerics, hyphens, and underscores.')]
    public function accepts_allowed_code(string $input): void
    {
        self::assertSame($input, MemberCode::of($input)->value);
    }

    #[Test]
    #[TestDox('Trims surrounding whitespace.')]
    public function trims_whitespace(): void
    {
        self::assertSame('M0001', MemberCode::of(' M0001 ')->value);
    }

    #[Test]
    #[TestWith([''])]
    #[TestWith(['   '])]
    #[TestDox('Rejects empty or whitespace-only codes with InvalidMemberCode.')]
    public function rejects_empty(string $input): void
    {
        $this->expectException(InvalidMemberCode::class);

        MemberCode::of($input);
    }

    #[Test]
    #[TestWith(['has space'])]
    #[TestWith(['bad/slash'])]
    #[TestWith(['bad.dot'])]
    #[TestDox('Rejects codes with characters outside [A-Za-z0-9_-]; message does not echo the raw input.')]
    public function rejects_illegal_characters(string $input): void
    {
        try {
            MemberCode::of($input);
            self::fail('Expected InvalidMemberCode.');
        } catch (InvalidMemberCode $e) {
            self::assertStringNotContainsString($input, $e->getMessage());
        }
    }

    #[Test]
    #[TestDox('Rejects codes longer than 32 characters; message does not echo the raw input.')]
    public function rejects_overlong(): void
    {
        $input = str_repeat('a', 33);

        try {
            MemberCode::of($input);
            self::fail('Expected InvalidMemberCode.');
        } catch (InvalidMemberCode $e) {
            self::assertStringNotContainsString($input, $e->getMessage());
        }
    }

    #[Test]
    #[TestDox('Equals another code with the same value.')]
    public function equals(): void
    {
        self::assertTrue(MemberCode::of('M0001')->equals(MemberCode::of('M0001')));
        self::assertFalse(MemberCode::of('M0001')->equals(MemberCode::of('M0002')));
    }

    #[Test]
    #[TestDox('Stringifies to the stored value.')]
    public function stringifies(): void
    {
        self::assertSame('M0001', (string) MemberCode::of('M0001'));
    }
}
