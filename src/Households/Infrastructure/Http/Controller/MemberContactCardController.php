<?php

declare(strict_types=1);

namespace App\Households\Infrastructure\Http\Controller;

use App\Households\Application\Command\UpdateMemberContact;
use App\Households\Application\Query\Port\MemberDetail;
use App\Households\Domain\Exception\HouseholdNotFound;
use App\Households\Domain\Exception\InvalidHouseholdId;
use App\Households\Domain\Exception\InvalidMemberId;
use App\Households\Domain\Exception\MemberNotFound;
use App\Households\Infrastructure\Http\Form\UpdateMemberContactFormType;
use App\Households\Infrastructure\Http\Form\UpdateMemberContactInput;
use App\Shared\Domain\Exception\InvalidEmailAddress;
use App\Shared\Domain\Exception\InvalidPhoneNumber;
use App\Shared\Domain\Exception\SharedDomainException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * HTTP adapter for the member detail page's Contact sub-card edit flow
 * (LRA-204) — email and phone. Follows the same read/edit/submit shape as
 * the Profile card, as an independent HTMX swap target nested inside it,
 * so an in-progress identity edit and an in-progress contact edit never
 * clobber each other. Split out of {@see MemberDetailController}
 * (LRA-235) — the Contact sub-card is one of four independently editable
 * cards on that page, each a separate reason to change.
 *
 * The controller stays thin: dispatches {@see \App\Households\Application\Query\GetMemberDetail} via the
 * `query.bus` and {@see UpdateMemberContact} via the `command.bus`, catches
 * the domain exceptions that bubble out of either bus, and translates
 * them to HTTP status codes (404 for missing aggregates, 422 with inline
 * form errors for validation failures).
 */
final class MemberContactCardController extends AbstractController
{
    use DispatchesHouseholdMessages;
    use RendersMemberCardPartials;

    private const string TEMPLATE_CONTACT_EDIT = 'households/detail/_contact_sub_card_edit.html.twig';

    /**
     * HTMX response header (LRA-153). Success responses set it so
     * assets/app.js can announce the outcome of an in-place update in the
     * shared #lr-live region without a focus change.
     */
    private const string HEADER_HX_TRIGGER = 'HX-Trigger';

    private const string HX_TRIGGER_CONTACT_SAVED = 'contactSaved';

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
     * HTMX partial endpoint that returns the Contact sub-card edit-mode
     * form, pre-populated with the member's current email and phone. The
     * Edit Contact button swaps `#contact-sub-card-body` with this
     * response; submission posts to {@see self::submitContact()}.
     */
    #[Route(
        '/admin/users/{householdId}/{memberId}/contact/edit',
        name: 'member_contact_edit_form',
        requirements: [
            'householdId' => self::UUID_V7_REGEX,
            'memberId'    => self::UUID_V7_REGEX,
        ],
        methods: ['GET'],
    )]
    public function editContactForm(string $householdId, string $memberId): Response
    {
        $detail = $this->findDetailOrFail($householdId, $memberId);

        $input = $this->inputFromContact($detail);
        $form = $this->createForm(UpdateMemberContactFormType::class, $input);

        return $this->render(self::TEMPLATE_CONTACT_EDIT, [
            'form' => $form->createView(),
            'householdId' => $householdId,
            'memberId' => $memberId,
        ]);
    }

    /**
     * Handles the Contact sub-card edit submission. On validation failure
     * or a domain exception re-renders the edit partial at HTTP 422 with
     * inline form errors. On success re-dispatches {@see \App\Households\Application\Query\GetMemberDetail}
     * and returns the read-mode partial at HTTP 200, so the sub-card swaps
     * back to read mode with the updated values.
     */
    #[Route(
        '/admin/users/{householdId}/{memberId}/contact',
        name: 'member_contact_submit',
        requirements: [
            'householdId' => self::UUID_V7_REGEX,
            'memberId'    => self::UUID_V7_REGEX,
        ],
        methods: ['POST'],
    )]
    public function submitContact(string $householdId, string $memberId, Request $request): Response
    {
        $input = new UpdateMemberContactInput();
        $form = $this->createForm(UpdateMemberContactFormType::class, $input);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->dispatchCommandUnwrapping(new UpdateMemberContact(
                    householdId: $householdId,
                    memberId: $memberId,
                    email: $input->email,
                    phone: $input->phone,
                ));

                $response = $this->render('households/detail/_contact_sub_card_read.html.twig', [
                    'detail' => $this->runQuery($householdId, $memberId),
                ]);
                $response->headers->set(self::HEADER_HX_TRIGGER, self::HX_TRIGGER_CONTACT_SAVED);

                return $response;
            } catch (MemberNotFound | HouseholdNotFound | InvalidHouseholdId | InvalidMemberId) {
                throw $this->createNotFoundException(self::MEMBER_NOT_FOUND_MESSAGE);
            } catch (InvalidEmailAddress $exception) {
                $form->get('email')->addError(new FormError($exception->getMessage()));
            } catch (InvalidPhoneNumber $exception) {
                $form->get('phone')->addError(new FormError($exception->getMessage()));
            } catch (SharedDomainException $exception) {
                $form->addError(new FormError($exception->getMessage()));
            }
        }

        return $this->renderEditPartial(
            self::TEMPLATE_CONTACT_EDIT,
            $form,
            $householdId,
            $memberId,
            Response::HTTP_UNPROCESSABLE_ENTITY,
        );
    }

    private function inputFromContact(MemberDetail $detail): UpdateMemberContactInput
    {
        $input = new UpdateMemberContactInput();
        $input->email = $detail->profile->email;
        $input->phone = $detail->profile->phone;

        return $input;
    }
}
