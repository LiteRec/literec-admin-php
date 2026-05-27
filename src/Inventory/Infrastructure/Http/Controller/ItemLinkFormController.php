<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Http\Controller;

use App\Inventory\Application\Command\LinkItem;
use App\Inventory\Application\Command\UnlinkItem;
use App\Inventory\Domain\Exception\DuplicateItemLink;
use App\Inventory\Domain\Exception\InventoryItemNotFound;
use App\Inventory\Domain\Exception\ItemLinkNotFound;
use App\Inventory\Domain\Exception\LinkToSelfForbidden;
use App\Inventory\Infrastructure\Http\Form\ItemLinkFormInput;
use App\Inventory\Infrastructure\Http\Form\ItemLinkFormType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Throwable;

/**
 * HTTP adapter for the LRA-89 "Link Item" HTMX dialog. Each link is
 * attached to a master inventory item; the operator picks the linked
 * item id, constraints, and optional inclusion window. Unlinking is a
 * DELETE on the link row, dispatched from the item-detail page where
 * link rows live (LRA-84).
 */
final class ItemLinkFormController extends AbstractController
{
    use HandleTrait {
        handle as private dispatchCommand;
    }

    private const string UUID_V7_REQUIREMENT = '[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}';

    private const string DIALOG_TEMPLATE = 'inventory/links/_dialog.html.twig';

    private const string MODAL_TITLE_CREATE = 'Link Item';

    private const string SUBMIT_LABEL_CREATE = 'Create Link';

    private const string ITEM_NOT_FOUND_MESSAGE = 'Inventory item not found.';

    private const string LINK_NOT_FOUND_MESSAGE = 'Item link not found.';

    private const string GENERIC_SAVE_FAILURE = 'Unable to save item link. Please try again.';

    private const string HX_TRIGGER_EVENT = 'linkSaved';

    private const string MODE_CREATE = 'create';

    private const string LINKED_ITEM_FIELD = 'linkedItemId';

    public function __construct(MessageBusInterface $commandBus)
    {
        $this->messageBus = $commandBus;
    }

    #[Route(
        '/admin/inventory/{itemId}/links/new',
        name: 'inventory_link_new',
        requirements: ['itemId' => self::UUID_V7_REQUIREMENT],
        methods: ['GET'],
    )]
    #[IsGranted('manage_inventory')]
    public function newForm(string $itemId): Response
    {
        $form = $this->createForm(ItemLinkFormType::class, new ItemLinkFormInput());

        return $this->render(self::DIALOG_TEMPLATE, [
            'form' => $form->createView(),
            'mode' => self::MODE_CREATE,
            'formAction' => $this->generateUrl('inventory_link_create', ['itemId' => $itemId]),
            'modalTitle' => self::MODAL_TITLE_CREATE,
            'submitLabel' => self::SUBMIT_LABEL_CREATE,
        ]);
    }

    #[Route(
        '/admin/inventory/{itemId}/links',
        name: 'inventory_link_create',
        requirements: ['itemId' => self::UUID_V7_REQUIREMENT],
        methods: ['POST'],
    )]
    #[IsGranted('manage_inventory')]
    public function create(string $itemId, Request $request): Response
    {
        $input = new ItemLinkFormInput();
        $form = $this->createForm(ItemLinkFormType::class, $input);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->reRenderForm($form, $itemId);
        }

        try {
            $this->dispatchCommandUnwrapping(new LinkItem(
                masterItemId: $itemId,
                linkedItemId: (string) $input->linkedItemId,
                reservedQuantityUnits: $input->reservedQuantityUnits,
                unlimited: $input->unlimited,
                minRequiredUnits: $input->minRequiredUnits,
                maxPerPurchaseUnits: $input->maxPerPurchaseUnits,
                includeUntilIso: $input->includeUntilIso,
            ));
        } catch (InventoryItemNotFound) {
            throw $this->createNotFoundException(self::ITEM_NOT_FOUND_MESSAGE);
        } catch (DuplicateItemLink | LinkToSelfForbidden $exception) {
            $form->get(self::LINKED_ITEM_FIELD)->addError(new FormError($exception->getMessage()));

            return $this->reRenderForm($form, $itemId);
        } catch (Throwable) {
            $form->addError(new FormError(self::GENERIC_SAVE_FAILURE));

            return $this->reRenderForm($form, $itemId);
        }

        return $this->savedResponse();
    }

    #[Route(
        '/admin/inventory/{itemId}/links/{linkId}',
        name: 'inventory_link_unlink',
        requirements: [
            'itemId' => self::UUID_V7_REQUIREMENT,
            'linkId' => self::UUID_V7_REQUIREMENT,
        ],
        methods: ['DELETE'],
    )]
    #[IsGranted('manage_inventory')]
    public function unlink(string $itemId, string $linkId): Response
    {
        // `itemId` participates in the URL only to disambiguate the
        // resource owner for routing/RBAC purposes; the UnlinkItem
        // command is keyed by `linkId` alone since each link has a
        // unique id within the system.
        unset($itemId);

        try {
            $this->dispatchCommandUnwrapping(new UnlinkItem(itemLinkId: $linkId));
        } catch (ItemLinkNotFound) {
            throw $this->createNotFoundException(self::LINK_NOT_FOUND_MESSAGE);
        }

        return $this->savedResponse();
    }

    /**
     * Messenger wraps handler exceptions in HandlerFailedException;
     * unwrap to surface the original domain exception to the caller.
     */
    private function dispatchCommandUnwrapping(object $command): mixed
    {
        try {
            return $this->dispatchCommand($command);
        } catch (HandlerFailedException $wrapper) {
            $nested = $wrapper->getPrevious();
            if ($nested instanceof Throwable) {
                throw $nested;
            }
            throw $wrapper;
        }
    }

    /**
     * @template TData
     * @param FormInterface<TData> $form
     */
    private function reRenderForm(FormInterface $form, string $itemId): Response
    {
        return $this->render(
            self::DIALOG_TEMPLATE,
            [
                'form' => $form->createView(),
                'mode' => self::MODE_CREATE,
                'formAction' => $this->generateUrl('inventory_link_create', ['itemId' => $itemId]),
                'modalTitle' => self::MODAL_TITLE_CREATE,
                'submitLabel' => self::SUBMIT_LABEL_CREATE,
            ],
            new Response(null, Response::HTTP_UNPROCESSABLE_ENTITY),
        );
    }

    private function savedResponse(): Response
    {
        $response = new Response('', Response::HTTP_OK);
        $response->headers->set('HX-Trigger', self::HX_TRIGGER_EVENT);
        $response->headers->set('HX-Reswap', 'none');
        return $response;
    }
}
