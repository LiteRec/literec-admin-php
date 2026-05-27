<?php

declare(strict_types=1);

namespace App\Tests\Support\Fake;

use RuntimeException;

/**
 * Raised by {@see InMemoryStockMovementLedger::recordConsumed()} when
 * the (transaction_id, item_id, facility_code) tuple already exists in
 * memory. Mirrors the production behaviour where Postgres raises a
 * {@see \Doctrine\DBAL\Exception\UniqueConstraintViolationException}
 * from the partial UNIQUE index on inventory_stock_movements.
 *
 * Lives alongside the in-memory fake so the test surface stays
 * cohesive: every consumer of the fake imports both the fake and the
 * exception from the same namespace.
 */
final class DuplicateConsumeKeyException extends RuntimeException
{
}
