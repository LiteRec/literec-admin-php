<?php

declare(strict_types=1);

namespace App\Households\Domain\ValueObject;

enum Salutation: string
{
    case Mr = 'MR';
    case Mrs = 'MRS';
    case Ms = 'MS';
    case Mx = 'MX';
    case Dr = 'DR';
    case Rev = 'REV';
}
