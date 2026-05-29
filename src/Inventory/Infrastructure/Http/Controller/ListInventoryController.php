<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Http\Controller;

use App\Inventory\Application\Query\ListInventory;
use App\Inventory\Application\Query\View\InventoryListPage;
use InvalidArgumentException;
use LogicException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * HTTP adapter for the Inventory list page (LRA-85).
 *
 * Two routes share the same query dispatch:
 *   - `inventory_index` (`GET /admin/inventory`) renders the full page shell.
 *   - `inventory_table` (`GET /admin/inventory/_table`) returns only the
 *     table partial, used by HTMX to swap into `#inventory-table` when
 *     filters, sort, or pagination change.
 *
 * The controller is intentionally thin: it decodes the query string into a
 * primitive {@see ListInventory} query DTO, dispatches via the `query.bus`,
 * and hands the {@see InventoryListPage} projection to Twig. All business
 * logic lives behind the read-model port (LRA-97).
 */
final class ListInventoryController extends AbstractController
{
    use HandleTrait {
        handle as private dispatchQuery;
    }

    /** @var list<string> */
    private const ALLOWED_SORT = ['name', 'code', 'quantity', '-name', '-code', '-quantity'];

    private const MAX_PAGE_SIZE = 200;

    private const DEFAULT_PAGE_SIZE = 50;

    public function __construct(MessageBusInterface $queryBus)
    {
        $this->messageBus = $queryBus;
    }

    #[Route('/admin/inventory', name: 'inventory_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        try {
            $query = $this->buildQuery($request);
        } catch (InvalidArgumentException $exception) {
            return new Response($exception->getMessage(), Response::HTTP_BAD_REQUEST);
        }

        $page = $this->runQuery($query);

        return $this->render('inventory/list.html.twig', [
            'page' => $page,
            'query' => $query,
        ]);
    }

    #[Route('/admin/inventory/_table', name: 'inventory_table', methods: ['GET'])]
    public function table(Request $request): Response
    {
        try {
            $query = $this->buildQuery($request);
        } catch (InvalidArgumentException $exception) {
            return new Response($exception->getMessage(), Response::HTTP_BAD_REQUEST);
        }

        $page = $this->runQuery($query);

        return $this->render('inventory/list/_table.html.twig', [
            'page' => $page,
            'query' => $query,
        ]);
    }

    /**
     * Runs the ListInventory query through the configured query bus.
     *
     * HandleTrait's protected handle() expects exactly one handler to have
     * processed the message; wrapping the dispatch in a small helper keeps
     * the action methods focused on HTTP concerns.
     */
    private function runQuery(ListInventory $query): InventoryListPage
    {
        $result = $this->dispatchQuery($query);

        if (! $result instanceof InventoryListPage) {
            throw new LogicException(sprintf(
                'ListInventory handler returned %s, expected %s.',
                get_debug_type($result),
                InventoryListPage::class,
            ));
        }

        return $result;
    }

    private function buildQuery(Request $request): ListInventory
    {
        $query = $request->query;

        $search = (string) $query->get('search', '');

        $sortRaw = (string) $query->get('sort', 'name');
        $sort = in_array($sortRaw, self::ALLOWED_SORT, true) ? $sortRaw : 'name';

        $pageNumber = max(1, $query->getInt('page', 1));
        $pageSize = $query->getInt('pageSize', self::DEFAULT_PAGE_SIZE);
        if ($pageSize < 1) {
            $pageSize = 1;
        }
        if ($pageSize > self::MAX_PAGE_SIZE) {
            $pageSize = self::MAX_PAGE_SIZE;
        }

        $archivedPresent = $query->has('archived');
        $archivedRaw = $archivedPresent ? $query->get('archived') : null;

        return new ListInventory(
            search: $search,
            facilityCode: self::stringOrNull($query->get('facilityCode')),
            groupId: self::stringOrNull($query->get('groupId')),
            kind: self::stringOrNull($query->get('kind')),
            archived: self::archivedTriState($archivedRaw, $archivedPresent),
            sort: $sort,
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

    /**
     * Distinguishes "not set" (null) from explicit false. If the query
     * param is absent the filter is unset; if present with a truthy
     * sentinel value (1/true/on/yes) the filter is true; any other
     * present value is treated as false.
     */
    private static function archivedTriState(mixed $value, bool $present): ?bool
    {
        if (! $present) {
            return null;
        }

        if (is_bool($value)) {
            return $value;
        }

        return (is_string($value) || is_int($value))
            && in_array((string) $value, ['1', 'true', 'on', 'yes'], true);
    }
}
