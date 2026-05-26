<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Exception;

/**
 * Marker for every PurchaseOrder-aggregate domain exception.
 *
 * Sits below {@see InventoryDomainException} (and therefore
 * {@see App\Shared\Domain\Exception\SharedDomainException}) so callers
 * that catch the broader marker still observe PO failures.
 */
interface PurchaseOrderException extends InventoryDomainException
{
}
