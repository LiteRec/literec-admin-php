<?php

declare(strict_types=1);

namespace App\Inventory\Domain\ValueObject;

/**
 * Why a stock movement occurred.
 *
 * Consume reasons (SALE / RENTAL_CHECKOUT) and internal-movement reasons
 * (ADJUSTMENT, TRANSFER_OUT, TRANSFER_IN, RETURN) cover the pre-LRA-94
 * surface used by InventoryItem::consume(), receive(), and transfer().
 *
 * RECEIPT and PO_RECEIPT are added by LRA-94 to cover the receive
 * direction so the new inventory_stock_movements ledger can declare its
 * `reason` column NOT NULL even for non-consume rows. RECEIPT covers
 * manual receives (ReceiveStockManually with no PO line). PO_RECEIPT
 * covers receipts driven by ReceivePurchaseOrderLine — distinguished
 * here so the LRA-91 Entry Log report can filter the two paths without
 * joining purchase orders back in.
 */
enum StockMovementReason: string
{
    case SALE = 'sale';
    case RENTAL_CHECKOUT = 'rental_checkout';
    case RETURN = 'return';
    case ADJUSTMENT = 'adjustment';
    case TRANSFER_OUT = 'transfer_out';
    case TRANSFER_IN = 'transfer_in';
    case RECEIPT = 'receipt';
    case PO_RECEIPT = 'po_receipt';
}
