<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Http\Controller;

use App\Inventory\Application\Command\ArchiveCombo;
use App\Inventory\Application\Command\DefineCombo;
use App\Inventory\Application\Command\UpdateComboComponents;
use App\Inventory\Domain\Exception\ComboCycleDetected;
use App\Inventory\Domain\Exception\ComboMayNotContainCombo;
use App\Inventory\Domain\Exception\ComboNotFound;
use App\Inventory\Domain\Exception\ComboRequiresComponents;
use App\Inventory\Domain\Exception\InvalidComboComponent;
use App\Inventory\Infrastructure\Http\Form\ComboComponentInput;
use App\Inventory\Infrastructure\Http\Form\ComboFormInput;
use App\Inventory\Infrastructure\Http\Form\ComboFormType;
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
 * HTTP adapter for the LRA-89 "Create / Edit Combo" HTMX dialog. The
 * controller is intentionally thin: it builds a primitive command DTO,
 * dispatches it via the configured `command.bus`, translates domain
 * failures into either field-level form errors (HTTP 422) or stable
 * status codes (404 for unknown combo), and on success returns an
 * empty 200 with `HX-Trigger: comboSaved` so the page's
 * `#inventory-table` region auto-refreshes and the modal dismisses
 * itself.
 *
 * Read-side projection caveat (deliberate scope choice for LRA-89):
 * the Inventory context does not yet ship a `GetCombo` query, so the
 * Edit dialog renders with an empty components grid. The operator
 * re-enters the desired component set; the
 * {@see UpdateComboComponents} handler then replaces the persisted
 * list atomically. A follow-up ticket can pre-fill the grid once a
 * read model exists.
 */
final class ComboFormController extends AbstractController
{
    use HtmxFormDialogResponses;
    use HandleTrait {
        handle as private dispatchCommand;
    }

    private const string UUID_V7_REQUIREMENT = '[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}';

    private const string DIALOG_TEMPLATE = 'inventory/combos/_dialog.html.twig';

    private const string MODAL_TITLE_CREATE = 'New Combo';

    private const string MODAL_TITLE_EDIT = 'Edit Combo Components';

    private const string SUBMIT_LABEL_CREATE = 'Create Combo';

    private const string SUBMIT_LABEL_EDIT = 'Save Components';

    private const string NOT_FOUND_MESSAGE = 'Combo not found.';

    private const string GENERIC_SAVE_FAILURE = 'Unable to save combo. Please try again.';

    private const string HX_TRIGGER_EVENT = 'comboSaved';

    private const string MODE_CREATE = 'create';

    private const string MODE_EDIT = 'edit';

    private const string COMPONENTS_FIELD = 'components';

    public function __construct(MessageBusInterface $commandBus)
    {
        $this->messageBus = $commandBus;
    }

    #[Route('/admin/inventory/combos/new', name: 'inventory_combo_new', methods: ['GET'])]
    #[IsGranted('manage_inventory')]
    public function newForm(): Response
    {
        $input = new ComboFormInput();
        // Seed one empty component row so the operator sees the grid
        // header without having to click "Add component" first.
        $input->components = [new ComboComponentInput()];

        $form = $this->createForm(ComboFormType::class, $input);

        return $this->render(self::DIALOG_TEMPLATE, [
            'form' => $form->createView(),
            'mode' => self::MODE_CREATE,
            'formAction' => $this->generateUrl('inventory_combo_create'),
            'modalTitle' => self::MODAL_TITLE_CREATE,
            'submitLabel' => self::SUBMIT_LABEL_CREATE,
        ]);
    }

    #[Route('/admin/inventory/combos/new', name: 'inventory_combo_create', methods: ['POST'])]
    #[IsGranted('manage_inventory')]
    public function create(Request $request): Response
    {
        $input = new ComboFormInput();
        $form = $this->createForm(ComboFormType::class, $input);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->reRenderForm(
                $form,
                self::MODE_CREATE,
                $this->generateUrl('inventory_combo_create'),
                self::MODAL_TITLE_CREATE,
                self::SUBMIT_LABEL_CREATE,
            );
        }

        try {
            $this->dispatchCommandUnwrapping(new DefineCombo(
                listingId: (string) $input->parentListingId,
                components: $this->mapComponents($input),
            ));
        } catch (
            ComboRequiresComponents
            | InvalidComboComponent
            | ComboMayNotContainCombo
            | ComboCycleDetected $exception
        ) {
            $form->get(self::COMPONENTS_FIELD)->addError(new FormError($exception->getMessage()));

            return $this->reRenderForm(
                $form,
                self::MODE_CREATE,
                $this->generateUrl('inventory_combo_create'),
                self::MODAL_TITLE_CREATE,
                self::SUBMIT_LABEL_CREATE,
            );
        } catch (Throwable) {
            $form->addError(new FormError(self::GENERIC_SAVE_FAILURE));

            return $this->reRenderForm(
                $form,
                self::MODE_CREATE,
                $this->generateUrl('inventory_combo_create'),
                self::MODAL_TITLE_CREATE,
                self::SUBMIT_LABEL_CREATE,
            );
        }

        return $this->savedResponse();
    }

    #[Route(
        '/admin/inventory/combos/{comboId}/edit',
        name: 'inventory_combo_edit',
        requirements: ['comboId' => self::UUID_V7_REQUIREMENT],
        methods: ['GET'],
    )]
    #[IsGranted('manage_inventory')]
    public function editForm(string $comboId): Response
    {
        // No GetCombo read query exists yet; render an empty edit form
        // and let the operator re-enter components. The update handler
        // replaces the persisted list atomically.
        $input = new ComboFormInput();
        $input->components = [new ComboComponentInput()];

        $form = $this->createForm(ComboFormType::class, $input);

        return $this->render(self::DIALOG_TEMPLATE, [
            'form' => $form->createView(),
            'mode' => self::MODE_EDIT,
            'formAction' => $this->generateUrl('inventory_combo_update', ['comboId' => $comboId]),
            'modalTitle' => self::MODAL_TITLE_EDIT,
            'submitLabel' => self::SUBMIT_LABEL_EDIT,
        ]);
    }

    #[Route(
        '/admin/inventory/combos/{comboId}/edit',
        name: 'inventory_combo_update',
        requirements: ['comboId' => self::UUID_V7_REQUIREMENT],
        methods: ['POST'],
    )]
    #[IsGranted('manage_inventory')]
    public function update(string $comboId, Request $request): Response
    {
        $input = new ComboFormInput();
        $form = $this->createForm(ComboFormType::class, $input);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->reRenderForm(
                $form,
                self::MODE_EDIT,
                $this->generateUrl('inventory_combo_update', ['comboId' => $comboId]),
                self::MODAL_TITLE_EDIT,
                self::SUBMIT_LABEL_EDIT,
            );
        }

        try {
            $this->dispatchCommandUnwrapping(new UpdateComboComponents(
                comboId: $comboId,
                components: $this->mapComponents($input),
            ));
        } catch (ComboNotFound) {
            throw $this->createNotFoundException(self::NOT_FOUND_MESSAGE);
        } catch (
            ComboRequiresComponents
            | InvalidComboComponent
            | ComboMayNotContainCombo
            | ComboCycleDetected $exception
        ) {
            $form->get(self::COMPONENTS_FIELD)->addError(new FormError($exception->getMessage()));

            return $this->reRenderForm(
                $form,
                self::MODE_EDIT,
                $this->generateUrl('inventory_combo_update', ['comboId' => $comboId]),
                self::MODAL_TITLE_EDIT,
                self::SUBMIT_LABEL_EDIT,
            );
        } catch (Throwable) {
            $form->addError(new FormError(self::GENERIC_SAVE_FAILURE));

            return $this->reRenderForm(
                $form,
                self::MODE_EDIT,
                $this->generateUrl('inventory_combo_update', ['comboId' => $comboId]),
                self::MODAL_TITLE_EDIT,
                self::SUBMIT_LABEL_EDIT,
            );
        }

        return $this->savedResponse();
    }

    #[Route(
        '/admin/inventory/combos/{comboId}',
        name: 'inventory_combo_archive',
        requirements: ['comboId' => self::UUID_V7_REQUIREMENT],
        methods: ['DELETE'],
    )]
    #[IsGranted('manage_inventory')]
    public function archive(string $comboId): Response
    {
        try {
            $this->dispatchCommandUnwrapping(new ArchiveCombo(comboId: $comboId));
        } catch (ComboNotFound) {
            throw $this->createNotFoundException(self::NOT_FOUND_MESSAGE);
        }

        return $this->savedResponse();
    }

    /**
     * @return list<array{itemId: string, quantityPerCombo: int}>
     */
    private function mapComponents(ComboFormInput $input): array
    {
        $rows = [];
        foreach ($input->components as $component) {
            $rows[] = [
                'itemId' => (string) $component->componentItemId,
                'quantityPerCombo' => (int) $component->quantityPerCombo,
            ];
        }
        return $rows;
    }
}
