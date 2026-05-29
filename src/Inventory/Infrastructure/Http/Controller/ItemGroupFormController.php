<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Http\Controller;

use App\Inventory\Application\Command\ArchiveItemGroup;
use App\Inventory\Application\Command\CreateItemGroup;
use App\Inventory\Application\Command\RecolorItemGroup;
use App\Inventory\Application\Command\RenameItemGroup;
use App\Inventory\Domain\Exception\DuplicateItemGroupName;
use App\Inventory\Domain\Exception\FacilityScopeEmpty;
use App\Inventory\Domain\Exception\InvalidItemGroupName;
use App\Inventory\Domain\Exception\InvalidPosColor;
use App\Inventory\Domain\Exception\ItemGroupNotFound;
use App\Inventory\Infrastructure\Http\Form\ItemGroupFormInput;
use App\Inventory\Infrastructure\Http\Form\ItemGroupFormType;
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
 * HTTP adapter for the LRA-89 "Create / Edit Item Group" HTMX dialog.
 *
 * Read-side projection caveat (deliberate scope choice for LRA-89):
 * the Inventory context does not yet ship a `GetItemGroup` query, so
 * the Edit dialog renders with an empty form. The operator re-enters
 * name + color; the rename and recolor handlers are dispatched
 * unconditionally and are idempotent (no-op if the value already
 * matches). Scope changes are intentionally NOT part of the Edit flow
 * — scope is fixed at creation time per the current domain contract.
 * A follow-up ticket can pre-fill the form once a read model exists.
 */
final class ItemGroupFormController extends AbstractController
{
    use HtmxFormDialogResponses;
    use HandleTrait {
        handle as private dispatchCommand;
    }

    private const string UUID_V7_REQUIREMENT = '[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}';

    private const string DIALOG_TEMPLATE = 'inventory/groups/_dialog.html.twig';

    private const string MODAL_TITLE_CREATE = 'New Item Group';

    private const string MODAL_TITLE_EDIT = 'Edit Item Group';

    private const string SUBMIT_LABEL_CREATE = 'Create Group';

    private const string SUBMIT_LABEL_EDIT = 'Save Changes';

    private const string NOT_FOUND_MESSAGE = 'Item group not found.';

    private const string GENERIC_SAVE_FAILURE = 'Unable to save item group. Please try again.';

    // Consumed by the HtmxFormDialogResponses trait via static::HX_TRIGGER_EVENT.
    private const string HX_TRIGGER_EVENT = 'groupSaved'; // NOSONAR

    private const string MODE_CREATE = 'create';

    private const string MODE_EDIT = 'edit';

    private const string FACILITY_CODE_FIELD = 'facilityCode';

    private const string NAME_FIELD = 'name';

    private const string COLOR_FIELD = 'colorHex';

    private const string FACILITY_REQUIRED_MESSAGE
        = 'A facility code is required when scope is set to "Specific facility".';

    public function __construct(MessageBusInterface $commandBus)
    {
        $this->messageBus = $commandBus;
    }

    #[Route('/admin/inventory/groups/new', name: 'inventory_group_new', methods: ['GET'])]
    #[IsGranted('manage_inventory')]
    public function newForm(): Response
    {
        $form = $this->createForm(ItemGroupFormType::class, new ItemGroupFormInput());

        return $this->render(self::DIALOG_TEMPLATE, [
            'form' => $form->createView(),
            'mode' => self::MODE_CREATE,
            'formAction' => $this->generateUrl('inventory_group_create'),
            'modalTitle' => self::MODAL_TITLE_CREATE,
            'submitLabel' => self::SUBMIT_LABEL_CREATE,
        ]);
    }

    #[Route('/admin/inventory/groups/new', name: 'inventory_group_create', methods: ['POST'])]
    #[IsGranted('manage_inventory')]
    public function create(Request $request): Response
    {
        $input = new ItemGroupFormInput();
        $form = $this->createForm(ItemGroupFormType::class, $input);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $facilityCodes = $this->extractFacilityCodes($input);

            if ($input->scope === ItemGroupFormInput::SCOPE_FACILITY && $facilityCodes === []) {
                $form->get(self::FACILITY_CODE_FIELD)->addError(new FormError(self::FACILITY_REQUIRED_MESSAGE));
            } else {
                try {
                    $this->dispatchCommandUnwrapping(new CreateItemGroup(
                        name: (string) $input->name,
                        colorHex: (string) $input->colorHex,
                        facilityCodes: $facilityCodes,
                    ));

                    return $this->savedResponse();
                } catch (DuplicateItemGroupName | InvalidItemGroupName $exception) {
                    $form->get(self::NAME_FIELD)->addError(new FormError($exception->getMessage()));
                } catch (InvalidPosColor $exception) {
                    $form->get(self::COLOR_FIELD)->addError(new FormError($exception->getMessage()));
                } catch (FacilityScopeEmpty $exception) {
                    $form->get(self::FACILITY_CODE_FIELD)->addError(new FormError($exception->getMessage()));
                } catch (Throwable) {
                    $form->addError(new FormError(self::GENERIC_SAVE_FAILURE));
                }
            }
        }

        return $this->reRenderForm(
            $form,
            self::MODE_CREATE,
            $this->generateUrl('inventory_group_create'),
            self::MODAL_TITLE_CREATE,
            self::SUBMIT_LABEL_CREATE,
        );
    }

    #[Route(
        '/admin/inventory/groups/{groupId}/edit',
        name: 'inventory_group_edit',
        requirements: ['groupId' => self::UUID_V7_REQUIREMENT],
        methods: ['GET'],
    )]
    #[IsGranted('manage_inventory')]
    public function editForm(string $groupId): Response
    {
        $form = $this->createForm(ItemGroupFormType::class, new ItemGroupFormInput());

        return $this->render(self::DIALOG_TEMPLATE, [
            'form' => $form->createView(),
            'mode' => self::MODE_EDIT,
            'formAction' => $this->generateUrl('inventory_group_update', ['groupId' => $groupId]),
            'modalTitle' => self::MODAL_TITLE_EDIT,
            'submitLabel' => self::SUBMIT_LABEL_EDIT,
        ]);
    }

    #[Route(
        '/admin/inventory/groups/{groupId}/edit',
        name: 'inventory_group_update',
        requirements: ['groupId' => self::UUID_V7_REQUIREMENT],
        methods: ['POST'],
    )]
    #[IsGranted('manage_inventory')]
    public function update(string $groupId, Request $request): Response
    {
        $input = new ItemGroupFormInput();
        $form = $this->createForm(ItemGroupFormType::class, $input);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->dispatchCommandUnwrapping(new RenameItemGroup(
                    groupId: $groupId,
                    name: (string) $input->name,
                ));
                $this->dispatchCommandUnwrapping(new RecolorItemGroup(
                    groupId: $groupId,
                    colorHex: (string) $input->colorHex,
                ));

                return $this->savedResponse();
            } catch (ItemGroupNotFound) {
                throw $this->createNotFoundException(self::NOT_FOUND_MESSAGE);
            } catch (DuplicateItemGroupName | InvalidItemGroupName $exception) {
                $form->get(self::NAME_FIELD)->addError(new FormError($exception->getMessage()));
            } catch (InvalidPosColor $exception) {
                $form->get(self::COLOR_FIELD)->addError(new FormError($exception->getMessage()));
            } catch (Throwable) {
                $form->addError(new FormError(self::GENERIC_SAVE_FAILURE));
            }
        }

        return $this->reRenderForm(
            $form,
            self::MODE_EDIT,
            $this->generateUrl('inventory_group_update', ['groupId' => $groupId]),
            self::MODAL_TITLE_EDIT,
            self::SUBMIT_LABEL_EDIT,
        );
    }

    #[Route(
        '/admin/inventory/groups/{groupId}',
        name: 'inventory_group_archive',
        requirements: ['groupId' => self::UUID_V7_REQUIREMENT],
        methods: ['DELETE'],
    )]
    #[IsGranted('manage_inventory')]
    public function archive(string $groupId): Response
    {
        try {
            $this->dispatchCommandUnwrapping(new ArchiveItemGroup(itemGroupId: $groupId));
        } catch (ItemGroupNotFound) {
            throw $this->createNotFoundException(self::NOT_FOUND_MESSAGE);
        }

        return $this->savedResponse();
    }

    /**
     * @return list<string>
     */
    private function extractFacilityCodes(ItemGroupFormInput $input): array
    {
        if ($input->scope === ItemGroupFormInput::SCOPE_ALL) {
            return [];
        }

        $code = trim((string) $input->facilityCode);
        return $code === '' ? [] : [$code];
    }
}
