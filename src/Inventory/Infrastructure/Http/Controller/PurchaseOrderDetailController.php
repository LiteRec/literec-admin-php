<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Http\Controller;

use App\Inventory\Application\Command\CreatePurchaseOrder;
use App\Inventory\Application\Query\GetPurchaseOrderDetail;
use App\Inventory\Application\Query\View\PurchaseOrderDetailView;
use App\Inventory\Domain\Exception\PurchaseOrderNotFound;
use App\Inventory\Domain\ValueObject\PurchaseOrderId;
use App\Inventory\Infrastructure\Http\Form\PurchaseOrderFormType;
use App\Inventory\Infrastructure\Http\Form\PurchaseOrderInput;
use App\Inventory\Infrastructure\Http\Form\PurchaseOrderLineInput;
use LogicException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * HTTP adapter for the LRA-90 Purchase Order create + view-detail
 * pages.
 *
 * Three routes:
 *   - `po_new`   GET  — render the empty Create form.
 *   - `po_create` POST — dispatch {@see CreatePurchaseOrder} and
 *     redirect to the detail page for the new PO.
 *   - `po_detail` GET — render the detail page (header card + line
 *     table + lifecycle action panel).
 *
 * The Create flow uses a full POST/redirect rather than an HTMX
 * dialog because the form has unbounded lines and the resulting
 * detail page is the operator's natural next step; treating it as a
 * conventional page keeps the back-button behaviour intuitive.
 */
final class PurchaseOrderDetailController extends AbstractController
{
    use DispatchesCommandsUnwrapping;
    use DispatchesQueriesUnwrapping;
    use HandleTrait {
        handle as private dispatchCommand;
    }

    private const string UUID_V7_REQUIREMENT = '[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}';

    private const string PERMISSION_VIEW = 'view_inventory';

    private const string PERMISSION_MANAGE = 'manage_purchase_orders';

    private const string TEMPLATE_NEW = 'inventory/purchase-orders/new.html.twig';

    private const string TEMPLATE_DETAIL = 'inventory/purchase-orders/detail.html.twig';

    private const string NOT_FOUND_MESSAGE = 'Purchase order not found.';

    private const string ROUTE_DETAIL = 'po_detail';

    private const string ROUTE_CREATE = 'po_create';

    private const string PARAM_PO_ID = 'poId';

    public function __construct(
        MessageBusInterface $commandBus,
        // NOSONAR — consumed by DispatchesQueriesUnwrapping trait at $this->queryBus.
        private readonly MessageBusInterface $queryBus,
    ) {
        $this->messageBus = $commandBus;
    }

    #[Route('/admin/inventory/purchase-orders/new', name: 'po_new', methods: ['GET'])]
    #[IsGranted(self::PERMISSION_MANAGE)]
    public function newForm(): Response
    {
        $input = new PurchaseOrderInput();
        // Seed one empty line row so the operator sees the table header
        // without first clicking "Add line".
        $input->lines = [new PurchaseOrderLineInput()];

        $form = $this->createForm(PurchaseOrderFormType::class, $input);

        return $this->renderNewForm($form);
    }

    #[Route('/admin/inventory/purchase-orders/new', name: 'po_create', methods: ['POST'])]
    #[IsGranted(self::PERMISSION_MANAGE)]
    public function create(Request $request): Response
    {
        $input = new PurchaseOrderInput();
        $form = $this->createForm(PurchaseOrderFormType::class, $input);
        $form->handleRequest($request);

        if (! $form->isSubmitted() || ! $form->isValid()) {
            return $this->renderNewForm($form, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Let infrastructure / unexpected failures propagate so the
        // framework returns a 5xx — masking them as 422 hides real
        // incidents behind a client-error response.
        $newId = $this->dispatchCommandUnwrapping(new CreatePurchaseOrder(
            vendorId: (string) $input->vendorId,
            facilityCode: (string) $input->facilityCode,
            lines: $this->mapLines($input),
        ));

        if (! $newId instanceof PurchaseOrderId) {
            throw new LogicException(sprintf(
                'CreatePurchaseOrder handler returned %s, expected %s.',
                get_debug_type($newId),
                PurchaseOrderId::class,
            ));
        }

        return $this->redirectToRoute(self::ROUTE_DETAIL, [self::PARAM_PO_ID => $newId->value]);
    }

    #[Route(
        '/admin/inventory/purchase-orders/{poId}',
        name: 'po_detail',
        requirements: ['poId' => self::UUID_V7_REQUIREMENT],
        methods: ['GET'],
    )]
    #[IsGranted(self::PERMISSION_VIEW)]
    public function detail(string $poId): Response
    {
        try {
            $detail = $this->loadDetail($poId);
        } catch (PurchaseOrderNotFound) {
            throw $this->createNotFoundException(self::NOT_FOUND_MESSAGE);
        }

        return $this->render(self::TEMPLATE_DETAIL, [
            'detail' => $detail,
        ]);
    }

    private function loadDetail(string $poId): PurchaseOrderDetailView
    {
        return $this->dispatchQueryUnwrapping(
            new GetPurchaseOrderDetail($poId),
            PurchaseOrderDetailView::class,
        );
    }

    /**
     * @template TData
     * @param FormInterface<TData> $form
     */
    private function renderNewForm(FormInterface $form, int $status = Response::HTTP_OK): Response
    {
        return $this->render(
            self::TEMPLATE_NEW,
            [
                'form' => $form->createView(),
                'formAction' => $this->generateUrl(self::ROUTE_CREATE),
            ],
            new Response(null, $status),
        );
    }

    /**
     * @return list<array{itemId: string, orderedQuantityUnits: int, costPerUnitCents: int}>
     */
    private function mapLines(PurchaseOrderInput $input): array
    {
        $rows = [];
        foreach ($input->lines as $line) {
            $rows[] = [
                'itemId' => (string) $line->itemId,
                'orderedQuantityUnits' => (int) $line->orderedQuantityUnits,
                'costPerUnitCents' => (int) $line->costPerUnitCents,
            ];
        }
        return $rows;
    }
}
