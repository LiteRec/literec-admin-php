<?php

declare(strict_types=1);

namespace App\Tests\Support\Fake;

use App\Inventory\Application\WrapsOptimisticLock;

/**
 * Test-only adapter that re-exposes the private helpers on
 * {@see WrapsOptimisticLock} as public methods so the trait's
 * translation logic can be exercised directly. Production code never
 * holds an instance of this class.
 */
final class PublicWrapsOptimisticLockAdapter
{
    use WrapsOptimisticLock {
        wrapInventoryItemSave as public;
        wrapPurchaseOrderSave as public;
        wrapPurchaseOrderLineSave as public;
    }
}
