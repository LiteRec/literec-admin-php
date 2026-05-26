<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Exception;

use App\Inventory\Domain\ValueObject\Comment;
use DomainException;

final class InvalidComment extends DomainException implements InventoryDomainException
{
    public static function empty(): self
    {
        return new self('Comment value cannot be empty after trimming whitespace.');
    }

    public static function tooLong(int $length): self
    {
        return new self(sprintf(
            'Comment value is %d characters; maximum is %d.',
            $length,
            Comment::MAX_LENGTH,
        ));
    }
}
