<?php

declare(strict_types=1);

namespace App\Users\Infrastructure\Fixtures;

use Faker\Factory;
use Faker\Generator;

/**
 * Builds a Faker generator seeded for reproducible fixture loads.
 *
 * The seed comes from the FIXTURE_SEED env var (default 1) so repeated
 * runs against a clean DB emit identical Faker output. Identity (UUID)
 * determinism is handled separately by the seeded IdentityGenerator
 * adapters introduced in LRA-51 — Faker on its own only stabilises the
 * generated names, emails, and other non-id fields.
 */
final class SeededFakerFactory
{
    public static function create(int $seed = 1, string $locale = 'en_US'): Generator
    {
        $faker = Factory::create($locale);
        $faker->seed($seed);

        return $faker;
    }
}
