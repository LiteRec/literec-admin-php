<?php

declare(strict_types=1);

namespace App\Tests\Unit\Inventory\Domain\ValueObject;

use App\Inventory\Domain\Exception\InvalidComment;
use App\Inventory\Domain\ValueObject\Comment;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[Small]
final class CommentTest extends TestCase
{
    #[Test]
    #[TestDox('::of() trims surrounding whitespace.')]
    public function trims_surrounding_whitespace(): void
    {
        self::assertSame('hello', Comment::of('  hello  ')->value);
    }

    #[Test]
    #[TestDox('::of() accepts the maximum allowed length.')]
    public function accepts_max_length(): void
    {
        $value = str_repeat('a', Comment::MAX_LENGTH);

        self::assertSame(Comment::MAX_LENGTH, mb_strlen(Comment::of($value)->value, 'UTF-8'));
    }

    #[Test]
    #[TestDox('::of() counts multi-byte UTF-8 characters, not raw bytes.')]
    public function counts_multibyte_characters(): void
    {
        // Each "好" is a 3-byte UTF-8 character; counted as a single character.
        $value = str_repeat('好', Comment::MAX_LENGTH);

        self::assertSame(Comment::MAX_LENGTH, mb_strlen(Comment::of($value)->value, 'UTF-8'));
    }

    #[Test]
    #[TestDox('::equals() compares by canonical (trimmed) value.')]
    public function equals_compares_by_value(): void
    {
        self::assertTrue(Comment::of('x')->equals(Comment::of('  x  ')));
        self::assertFalse(Comment::of('x')->equals(Comment::of('y')));
    }

    #[Test]
    #[TestWith([''], 'empty string')]
    #[TestWith(['   '], 'whitespace only')]
    #[TestDox('::of() rejects empty input: $_dataName.')]
    public function rejects_empty(string $value): void
    {
        $this->expectException(InvalidComment::class);

        Comment::of($value);
    }

    #[Test]
    #[TestDox('::of() rejects input exceeding MAX_LENGTH.')]
    public function rejects_too_long(): void
    {
        $this->expectException(InvalidComment::class);

        Comment::of(str_repeat('a', Comment::MAX_LENGTH + 1));
    }
}
