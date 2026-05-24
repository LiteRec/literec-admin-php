<?php

declare(strict_types=1);

namespace App\Households\Domain\ValueObject;

enum Gender: string
{
    case Female = 'F';
    case Male = 'M';
    case Other = 'O';
    case Unspecified = 'U';
}
