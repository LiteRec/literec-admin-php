<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Http\Controller;

use App\Inventory\Application\Command\MarkPurchaseOrderSent;
use App\Inventory\Application\Command\ReceivePurchaseOrderLine;
use App\Inventory\Application\Command\VerifyDelivery;
use App\Inventory\Application\Query\GetPurchaseOrderDetail;
use App\Inventory\Application\Query\GetPurchaseOrderDetailHandler;
use App\Inventory\Application\Query\View\PurchaseOrderDetailView;
use App\Inventory\Domain\Exception\ConcurrentPurchaseOrderModification;
use App\Inventory\Domain\Exception\PurchaseOrderNotFound;
use DateTimeImmutable;
use DateTimeInterface;
use LogicException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * HTTP adapter for the LRA-90 Purchase Order lifecycle transitions:
 * send, receive-line, verify.
 *
 * Each action posts to its own endpoint; the controller:
 *   1. Loads the current detail view via the query bus.
 *   2. Defends-in-depth by checking the requested transition appears
 *      in {@see PurchaseOrderDetailView::$allowedTransitions} — if
 *      not, returns 409 with a friendly notice. This catches stale
 *      buttons (the operator opened the page, walked away, someone
 *      else moved the PO forward, and they then clicked).
 *   3. Dispatches the command, translating
 *      {@see ConcurrentPurchaseOrderModification} into 409 and any
 *      other domain failure into 422.
 *   4. On success returns the freshly-rendered detail body so HTMX
 *      can swap `#po-detail` and the lifecycle action panel updates.
 *
 * The success response also carries `HX-Trigger` so any other region
 * on the page (e.g. the list page if the operator navigated here from
 * it via HTMX) can refresh itself.
 */
final class PurchaseOrderLifecycleController extends AbstractController
{
    use DispatchesCommandsUnwrapping;
    use DispatchesQueriesUnwrapping;
    use HandleTrait {
        handle as private dispatchCommand;
    }

    private const string UUID_V7_REQUIREMENT = '[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}';

    private const string PERMISSION_MANAGE = 'manage_purchase_orders';

    private const string TEMPLATE_DETAIL_BODY = 'inventory/purchase-orders/_detail_body.html.twig';

    private const string NOT_FOUND_MESSAGE = 'Purchase order not found.';

    private const string CONCURRENT_NOTICE = 'Purchase order changed since you loaded the page — refreshing.';

    private const string GENERIC_FAILURE = 'Unable to apply purchase order action. Please try again.';

    private const string STALE_TRANSITION_NOTICE = 'That action is no longer available for this purchase order.';

    private const string HEADER_HX_TRIGGER = 'HX-Trigger';

    private const string HEADER_HX_RESWAP = 'HX-Reswap';

    private const string HX_TRIGGER_PO_SENT = 'poSent';

    private const string HX_TRIGGER_PO_LINE_RECEIVED = 'poLineReceived';

    private const string HX_TRIGGER_PO_VERIFIED = 'poVerified';

    private const string FORM_FIELD_RECEIVED_QUANTITY = 'receivedQuantityUnits';

    public function __construct(
        MessageBusInterface $commandBus,
        // Consumed by the DispatchesQueriesUnwrapping trait at $this->queryBus.
        private readonly MessageBusInterface $queryBus, // NOSONAR
    ) {
        $this->messageBus = $commandBus;
    }

    #[Route(
        '/admin/inventory/purchase-orders/{poId}/send',
        name: 'po_send',
        requirements: ['poId' => self::UUID_V7_REQUIREMENT],
        methods: ['POST'],
    )]
    #[IsGranted(self::PERMISSION_MANAGE)]
    public function send(string $poId): Response
    {
        $detail = $this->loadDetailOrThrow404($poId);

        if (! in_array(GetPurchaseOrderDetailHandler::TRANSITION_SEND, $detail->allowedTransitions, true)) {
            return $this->staleTransitionResponse();
        }

        $now = new DateTimeImmutable();
        $nowIso = $now->format(DateTimeInterface::ATOM);

        try {
            // The HTMX "Mark sent" button is a single-click action with no
            // arrival-date input — the operator's intent is "this PO is now
            // in flight". A future iteration can introduce a small dialog
            // that collects estimatedArrivalIso; for now it is intentionally
            // null and the aggregate records the omission.
            $this->dispatchCommandUnwrapping(new MarkPurchaseOrderSent(
                purchaseOrderId: $poId,
                sentAtIso: $nowIso,
                estimatedArrivalIso: null,
            ));
        } catch (ConcurrentPurchaseOrderModification) {
            return $this->concurrentResponse();
        }

        return $this->refreshedDetailResponse($poId, self::HX_TRIGGER_PO_SENT);
    }

    #[Route(
        '/admin/inventory/purchase-orders/{poId}/lines/{lineId}/receive',
        name: 'po_line_receive',
        requirements: [
            'poId' => self::UUID_V7_REQUIREMENT,
            'lineId' => self::UUID_V7_REQUIREMENT,
        ],
        methods: ['POST'],
    )]
    #[IsGranted(self::PERMISSION_MANAGE)]
    public function receiveLine(string $poId, string $lineId, Request $request): Response
    {
        $detail = $this->loadDetailOrThrow404($poId);

        if (! in_array(GetPurchaseOrderDetailHandler::TRANSITION_RECEIVE_LINE, $detail->allowedTransitions, true)) {
            return $this->staleTransitionResponse();
        }

        $quantity = $request->request->getInt(self::FORM_FIELD_RECEIVED_QUANTITY, 0);
        if ($quantity < 1) {
            return $this->genericFailureResponse(Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $now = new DateTimeImmutable();
        $nowIso = $now->format(DateTimeInterface::ATOM);

        try {
            $this->dispatchCommandUnwrapping(new ReceivePurchaseOrderLine(
                purchaseOrderId: $poId,
                lineId: $lineId,
                receivedQuantityUnits: $quantity,
                receivedAtIso: $nowIso,
            ));
        } catch (ConcurrentPurchaseOrderModification) {
            return $this->concurrentResponse();
        }

        return $this->refreshedDetailResponse($poId, self::HX_TRIGGER_PO_LINE_RECEIVED);
    }

    #[Route(
        '/admin/inventory/purchase-orders/{poId}/verify',
        name: 'po_verify',
        requirements: ['poId' => self::UUID_V7_REQUIREMENT],
        methods: ['POST'],
    )]
    #[IsGranted(self::PERMISSION_MANAGE)]
    public function verify(string $poId): Response
    {
        $detail = $this->loadDetailOrThrow404($poId);

        if (! in_array(GetPurchaseOrderDetailHandler::TRANSITION_VERIFY, $detail->allowedTransitions, true)) {
            return $this->staleTransitionResponse();
        }

        $user = $this->getUser();
        if (! $user instanceof UserInterface) {
            throw new LogicException('Verify requires an authenticated user.');
        }
        $verifiedByUserId = $user->getUserIdentifier();

        $now = new DateTimeImmutable();
        $nowIso = $now->format(DateTimeInterface::ATOM);

        try {
            $this->dispatchCommandUnwrapping(new VerifyDelivery(
                purchaseOrderId: $poId,
                verifiedByUserId: $verifiedByUserId,
                verifiedAtIso: $nowIso,
            ));
        } catch (ConcurrentPurchaseOrderModification) {
            return $this->concurrentResponse();
        }

        return $this->refreshedDetailResponse($poId, self::HX_TRIGGER_PO_VERIFIED);
    }

    private function loadDetailOrThrow404(string $poId): PurchaseOrderDetailView
    {
        try {
            return $this->loadDetail($poId);
        } catch (PurchaseOrderNotFound) {
            throw $this->createNotFoundException(self::NOT_FOUND_MESSAGE);
        }
    }

    private function loadDetail(string $poId): PurchaseOrderDetailView
    {
        return $this->dispatchQueryUnwrapping(
            new GetPurchaseOrderDetail($poId),
            PurchaseOrderDetailView::class,
        );
    }

    private function refreshedDetailResponse(string $poId, string $hxTrigger): Response
    {
        $detail = $this->loadDetail($poId);

        $response = $this->render(self::TEMPLATE_DETAIL_BODY, ['detail' => $detail]);
        $response->headers->set(self::HEADER_HX_TRIGGER, $hxTrigger);

        return $response;
    }

    private function staleTransitionResponse(): Response
    {
        $response = new Response(self::STALE_TRANSITION_NOTICE, Response::HTTP_CONFLICT);
        $response->headers->set(self::HEADER_HX_RESWAP, 'none');
        return $response;
    }

    private function concurrentResponse(): Response
    {
        $response = new Response(self::CONCURRENT_NOTICE, Response::HTTP_CONFLICT);
        $response->headers->set(self::HEADER_HX_RESWAP, 'none');
        return $response;
    }

    private function genericFailureResponse(int $status = Response::HTTP_UNPROCESSABLE_ENTITY): Response
    {
        $response = new Response(self::GENERIC_FAILURE, $status);
        $response->headers->set(self::HEADER_HX_RESWAP, 'none');
        return $response;
    }
}
