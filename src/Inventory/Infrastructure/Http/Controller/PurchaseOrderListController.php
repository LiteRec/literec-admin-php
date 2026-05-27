<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Http\Controller;

use App\Inventory\Application\Query\ListPurchaseOrders;
use App\Inventory\Application\Query\View\PurchaseOrderListPage;
use App\Inventory\Domain\PurchaseOrderStatus;
use LogicException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * HTTP adapter for the LRA-90 Purchase Orders list page.
 *
 * Two routes share the same query dispatch:
 *   - `po_index` (`GET /admin/inventory/purchase-orders`) renders the
 *     full page shell.
 *   - `po_table` (`GET /admin/inventory/purchase-orders/_table`)
 *     returns only the table partial, used by HTMX to swap into
 *     `#purchase-orders-table` when filters or pagination change.
 *
 * The controller decodes the query string into a primitive
 * {@see ListPurchaseOrders} DTO and hands the
 * {@see PurchaseOrderListPage} projection to Twig. All filtering and
 * paging logic lives behind the {@see \App\Inventory\Domain\PurchaseOrders}
 * port.
 */
final class PurchaseOrderListController extends AbstractController
{
    use HandleTrait {
        handle as private dispatchQuery;
    }

    private const string PERMISSION_VIEW = 'view_inventory';

    private const string TEMPLATE_INDEX = 'inventory/purchase-orders/list.html.twig';

    private const string TEMPLATE_TABLE = 'inventory/purchase-orders/list/_table.html.twig';

    private const int DEFAULT_PAGE_SIZE = 50;

    private const int MAX_PAGE_SIZE = 200;

    public function __construct(MessageBusInterface $queryBus)
    {
        $this->messageBus = $queryBus;
    }

    #[Route('/admin/inventory/purchase-orders', name: 'po_index', methods: ['GET'])]
    #[IsGranted(self::PERMISSION_VIEW)]
    public function index(Request $request): Response
    {
        $query = $this->buildQuery($request);
        $page = $this->runQuery($query);

        return $this->render(self::TEMPLATE_INDEX, [
            'page' => $page,
            'query' => $query,
            'statusCases' => PurchaseOrderStatus::cases(),
        ]);
    }

    #[Route('/admin/inventory/purchase-orders/_table', name: 'po_table', methods: ['GET'])]
    #[IsGranted(self::PERMISSION_VIEW)]
    public function table(Request $request): Response
    {
        $query = $this->buildQuery($request);
        $page = $this->runQuery($query);

        return $this->render(self::TEMPLATE_TABLE, [
            'page' => $page,
            'query' => $query,
        ]);
    }

    private function runQuery(ListPurchaseOrders $query): PurchaseOrderListPage
    {
        $result = $this->dispatchQuery($query);

        if (! $result instanceof PurchaseOrderListPage) {
            throw new LogicException(sprintf(
                'ListPurchaseOrders handler returned %s, expected %s.',
                get_debug_type($result),
                PurchaseOrderListPage::class,
            ));
        }

        return $result;
    }

    private function buildQuery(Request $request): ListPurchaseOrders
    {
        $query = $request->query;

        $pageNumber = max(1, $query->getInt('page', 1));
        $pageSize = $query->getInt('pageSize', self::DEFAULT_PAGE_SIZE);
        if ($pageSize < 1) {
            $pageSize = 1;
        }
        if ($pageSize > self::MAX_PAGE_SIZE) {
            $pageSize = self::MAX_PAGE_SIZE;
        }

        return new ListPurchaseOrders(
            vendorId: self::stringOrNull($query->get('vendorId')),
            status: self::stringOrNull($query->get('status')),
            facilityCode: self::stringOrNull($query->get('facilityCode')),
            pageNumber: $pageNumber,
            pageSize: $pageSize,
        );
    }

    private static function stringOrNull(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
