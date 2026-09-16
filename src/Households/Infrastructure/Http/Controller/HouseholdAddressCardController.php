<?php

declare(strict_types=1);

namespace App\Households\Infrastructure\Http\Controller;

use App\Households\Application\Command\UpdateHouseholdAddress;
use App\Households\Application\Query\Port\MemberDetail;
use App\Households\Domain\Exception\HouseholdNotFound;
use App\Households\Domain\Exception\InvalidAddress;
use App\Households\Domain\Exception\InvalidHouseholdId;
use App\Households\Domain\Exception\InvalidMemberId;
use App\Households\Domain\Exception\MemberNotFound;
use App\Households\Infrastructure\Http\Form\UpdateHouseholdAddressFormType;
use App\Households\Infrastructure\Http\Form\UpdateHouseholdAddressInput;
use App\Shared\Domain\Exception\SharedDomainException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * HTTP adapter for the member detail page's Address sub-card edit flow
 * (LRA-44): an HTMX-swapped edit form pre-populated with the household's
 * current address, and a POST that re-dispatches the read query and
 * returns the read partial on success. Split out of
 * {@see MemberDetailController} (LRA-235) — the Address sub-card is one
 * of four independently editable cards on that page, each a separate
 * reason to change.
 *
 * The controller stays thin: dispatches {@see \App\Households\Application\Query\GetMemberDetail} via the
 * `query.bus` and {@see UpdateHouseholdAddress} via the `command.bus`,
 * catches the domain exceptions that bubble out of either bus, and
 * translates them to HTTP status codes (404 for missing aggregates, 422
 * with inline form errors for validation failures).
 */
final class HouseholdAddressCardController extends AbstractController
{
    use DispatchesHouseholdMessages;
    use RendersMemberCardPartials;

    private const string TEMPLATE_ADDRESS_EDIT = 'households/detail/_address_sub_card_edit.html.twig';

    public function __construct(
        private readonly MessageBusInterface $queryBus,
        private readonly MessageBusInterface $commandBus,
    ) {
    }

    private function queryBus(): MessageBusInterface // NOSONAR
    {
        return $this->queryBus;
    }

    private function commandBus(): MessageBusInterface // NOSONAR
    {
        return $this->commandBus;
    }

    /**
     * HTMX partial endpoint that returns the Address sub-card edit-mode
     * form, pre-populated with the household's current address. The Edit
     * Address button swaps `#address-sub-card-body` with this response;
     * submission posts to {@see self::submitAddress()}.
     */
    #[Route(
        '/admin/users/{householdId}/{memberId}/address/edit',
        name: 'member_address_edit_form',
        requirements: [
            'householdId' => self::UUID_V7_REGEX,
            'memberId'    => self::UUID_V7_REGEX,
        ],
        methods: ['GET'],
    )]
    public function editAddressForm(string $householdId, string $memberId): Response
    {
        $detail = $this->findDetailOrFail($householdId, $memberId);

        $input = $this->inputFromAddress($detail);
        $form = $this->createForm(UpdateHouseholdAddressFormType::class, $input);

        return $this->render(self::TEMPLATE_ADDRESS_EDIT, [
            'form' => $form->createView(),
            'householdId' => $householdId,
            'memberId' => $memberId,
        ]);
    }

    /**
     * Handles the Address sub-card edit submission. On validation failure
     * or a domain exception re-renders the edit partial at HTTP 422 with
     * inline form errors. On success re-dispatches the read query and
     * returns the address sub-card read partial at HTTP 200, swapping the
     * sub-card back to read mode.
     *
     * Note: this endpoint is household-scoped (no memberId in the path)
     * because the address lives on the household aggregate. The form's
     * Cancel button still re-fetches the lower cards via the member-scoped
     * route, which is why the memberId travels through the edit-form
     * template even though the submit endpoint does not need it.
     */
    #[Route(
        '/admin/users/{householdId}/address',
        name: 'household_address_submit',
        requirements: [
            'householdId' => self::UUID_V7_REGEX,
        ],
        methods: ['POST'],
    )]
    public function submitAddress(string $householdId, Request $request): Response
    {
        $input = new UpdateHouseholdAddressInput();
        $form = $this->createForm(UpdateHouseholdAddressFormType::class, $input);
        $form->handleRequest($request);

        // memberId is needed for re-render paths (Cancel re-fetches the
        // member-scoped lower-cards route, success re-runs the detail
        // query). It travels as a hidden field on the address form because
        // the household-scoped submit URL itself does not encode it.
        $memberId = $this->readMemberIdFromRequest($request);

        if ($memberId === null) {
            throw $this->createNotFoundException(self::MEMBER_NOT_FOUND_MESSAGE);
        }

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->dispatchCommandUnwrapping(new UpdateHouseholdAddress(
                    householdId: $householdId,
                    street: (string) $input->street,
                    unit: $input->unit,
                    city: (string) $input->city,
                    state: (string) $input->state,
                    postalCode: (string) $input->postalCode,
                    country: (string) $input->country,
                ));

                return $this->render('households/detail/_address_sub_card_read.html.twig', [
                    'detail' => $this->runQuery($householdId, $memberId),
                ]);
            } catch (HouseholdNotFound | InvalidHouseholdId) {
                throw $this->createNotFoundException('Household not found.');
            } catch (MemberNotFound | InvalidMemberId) {
                throw $this->createNotFoundException(self::MEMBER_NOT_FOUND_MESSAGE);
            } catch (InvalidAddress $exception) {
                $this->applyAddressErrorToForm($form, $exception);
            } catch (SharedDomainException $exception) {
                $form->addError(new FormError($exception->getMessage()));
            }
        }

        return $this->renderEditPartial(
            self::TEMPLATE_ADDRESS_EDIT,
            $form,
            $householdId,
            $memberId,
            Response::HTTP_UNPROCESSABLE_ENTITY,
        );
    }

    /**
     * Reads the `member_context_id` hidden field from the address-form
     * payload and returns it when it matches the UUID v7 shape, or null
     * otherwise. Defensive: a missing or malformed value triggers a 404
     * upstream rather than letting an invalid id reach the read query.
     */
    private function readMemberIdFromRequest(Request $request): ?string
    {
        $value = $request->request->get('member_context_id');
        if (!is_string($value) || $value === '') {
            return null;
        }

        if (preg_match('/^' . self::UUID_V7_REGEX . '$/', $value) !== 1) {
            return null;
        }

        return $value;
    }

    private function inputFromAddress(MemberDetail $detail): UpdateHouseholdAddressInput
    {
        $input = new UpdateHouseholdAddressInput();
        $input->street = $detail->address->street;
        $input->unit = $detail->address->unit;
        $input->city = $detail->address->city;
        $input->state = $detail->address->state;
        $input->postalCode = $detail->address->postalCode;
        $input->country = $detail->address->country;

        return $input;
    }

    /**
     * Maps an InvalidAddress exception onto the most likely offending
     * field — heuristically, based on the message text used by the named
     * constructors on the exception. Falls back to a form-level error
     * when no field can be identified.
     *
     * @template TData
     * @param FormInterface<TData> $form
     */
    private function applyAddressErrorToForm(FormInterface $form, InvalidAddress $exception): void
    {
        $message = $exception->getMessage();
        $lower = strtolower($message);

        $fieldMap = [
            'postal'   => 'postalCode',
            'country'  => 'country',
            '"street"' => 'street',
            '"city"'   => 'city',
            '"state"'  => 'state',
            '"unit"'   => 'unit',
        ];

        foreach ($fieldMap as $needle => $field) {
            if (str_contains($lower, $needle) && $form->has($field)) {
                $form->get($field)->addError(new FormError($message));

                return;
            }
        }

        $form->addError(new FormError($message));
    }
}
