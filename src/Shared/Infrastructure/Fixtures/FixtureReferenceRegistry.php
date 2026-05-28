<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Fixtures;

use LogicException;
use OutOfBoundsException;

/**
 * In-memory registry for sharing fixture-generated identifiers
 * (value-object IDs) between fixture classes without going through
 * Doctrine's reference repository.
 *
 * Doctrine's {@see \Doctrine\Common\DataFixtures\ReferenceRepository}
 * requires registered references to be managed entities (it calls
 * `EntityManager::getClassMetadata()` on the class on read). Our
 * fixtures pass identifiers as value objects (e.g.
 * {@see \App\Inventory\Domain\ValueObject\VendorId}) which have no
 * Doctrine mapping; this registry sidesteps that limitation.
 *
 * The service is autowired by the container so fixtures share a
 * single instance for the lifetime of one load.
 */
final class FixtureReferenceRegistry
{
    /** @var array<string, object> */
    private array $references = [];

    public function set(string $key, object $value): void
    {
        $this->references[$key] = $value;
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $expectedClass
     *
     * @return T
     */
    public function get(string $key, string $expectedClass): object
    {
        if (!isset($this->references[$key])) {
            throw new OutOfBoundsException(sprintf(
                'No fixture reference registered under key "%s".',
                $key,
            ));
        }

        $value = $this->references[$key];
        if (!$value instanceof $expectedClass) {
            throw new LogicException(sprintf(
                'Fixture reference "%s" is %s, expected %s.',
                $key,
                $value::class,
                $expectedClass,
            ));
        }

        return $value;
    }

    public function clear(): void
    {
        $this->references = [];
    }
}
