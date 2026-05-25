<?php

declare(strict_types=1);

namespace App\Tests\Support\Trait;

use Generator;

/**
 * Shared UUID v7 test cases consumed by every Inventory identity VO test.
 *
 * The cases are copied from the Catalog ListingIdTest so that all UUID v7
 * VOs across the project pin down the same regex contract.
 */
trait UuidV7TestCases
{
    /**
     * @return Generator<string, array{string}>
     */
    public static function validUuidV7Cases(): Generator
    {
        yield 'all-lowercase canonical'    => ['019571bf-5d51-7000-b500-0123456789ab'];
        yield 'with leading zeros'         => ['00000000-0000-7000-8000-000000000000'];
        yield 'variant nibble 9'           => ['019571bf-5d51-7abc-9def-0123456789ab'];
        yield 'variant nibble a'           => ['019571bf-5d51-7abc-a012-0123456789ab'];
        yield 'variant nibble b (maximum)' => ['ffffffff-ffff-7fff-bfff-ffffffffffff'];
    }

    /**
     * @return Generator<string, array{string}>
     */
    public static function invalidUuidCases(): Generator
    {
        yield 'empty string'              => [''];
        yield 'plain text'                => ['not-a-uuid'];
        yield 'uuid v4 (version nibble 4)' => ['12345678-1234-4abc-9def-0123456789ab'];
        yield 'uuid v6 (version nibble 6)' => ['12345678-1234-6abc-9def-0123456789ab'];
        yield 'uppercase'                 => ['019571BF-5D51-7000-B500-0123456789AB'];
        yield 'missing hyphen'            => ['019571bf5d517000b5000123456789ab'];
        yield 'invalid variant nibble c'  => ['019571bf-5d51-7abc-c012-0123456789ab'];
    }
}
