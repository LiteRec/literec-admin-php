<?php

declare(strict_types=1);

namespace App\Households\Infrastructure\Http\Controller;

use App\Households\Application\Command\ChangeMemberResidency;
use App\Households\Application\Command\UpdateHouseholdAddress;
use App\Households\Application\Command\UpdateMemberContact;
use App\Households\Application\Command\UpdateMemberProfile;
use App\Households\Application\Port\MemberTransactionHistory;
use App\Households\Application\Query\Port\MemberDetail;
use App\Households\Domain\Exception\HouseholdNotFound;
use App\Households\Domain\Exception\InvalidAddress;
use App\Households\Domain\Exception\InvalidDateOfBirth;
use App\Households\Domain\Exception\InvalidHeight;
use App\Households\Domain\Exception\InvalidHouseholdId;
use App\Households\Domain\Exception\InvalidMemberId;
use App\Households\Domain\Exception\InvalidPersonName;
use App\Households\Domain\Exception\InvalidWeight;
use App\Households\Domain\Exception\MemberNotFound;
use App\Households\Domain\ValueObject\HouseholdId as HouseholdIdVo;
use App\Households\Domain\ValueObject\MemberId as MemberIdVo;
use App\Households\Infrastructure\Http\Form\AppliesPersonNameErrors;
use App\Households\Infrastructure\Http\Form\ChangeMemberResidencyFormType;
use App\Households\Infrastructure\Http\Form\ChangeMemberResidencyInput;
use App\Households\Infrastructure\Http\Form\UpdateHouseholdAddressFormType;
use App\Households\Infrastructure\Http\Form\UpdateHouseholdAddressInput;
use App\Households\Infrastructure\Http\Form\UpdateMemberContactFormType;
use App\Households\Infrastructure\Http\Form\UpdateMemberContactInput;
use App\Households\Infrastructure\Http\Form\UpdateMemberProfileFormType;
use App\Households\Infrastructure\Http\Form\UpdateMemberProfileInput;
use App\Shared\Domain\Exception\InvalidEmailAddress;
use App\Shared\Domain\Exception\InvalidPhoneNumber;
use App\Shared\Domain\Exception\SharedDomainException;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * HTTP adapter for the member detail page (LRA-41) and the in-card
 * Profile read/edit flow (LRA-43).
 *
 * Renders the composite shell that hosts the four card slots — Household
 * (LRA-42), Profile (LRA-43), Address & Residency (LRA-44), Transaction
 * History (LRA-45). The Profile card mutation flow lives entirely in this
 * controller: an HTMX-swapped read partial, an HTMX-swapped edit form,
 * and a POST that re-dispatches the read query and returns the read
 * partial on success. The Contact sub-card (LRA-204) — email and phone —
 * follows the same read/edit/submit shape as an independent HTMX swap
 * target nested inside the Profile card, so an in-progress identity edit
 * and an in-progress contact edit never clobber each other.
 *
 * The controller stays thin: dispatches {@see \App\Households\Application\Query\GetMemberDetail} via the
 * `query.bus` and {@see UpdateMemberProfile} via the `command.bus`, catches
 * the domain exceptions that bubble out of either bus, and translates them
 * to HTTP status codes (404 for missing aggregates, 422 with inline form
 * errors for validation failures). Route requirements enforce the UUID v7
 * shape of both identifiers so the value-object factories should not throw;
 * the catches on {@see InvalidHouseholdId} and {@see InvalidMemberId} are
 * defence in depth.
 */
final class MemberDetailController extends AbstractController
{
    use AppliesPersonNameErrors;
    use DispatchesHouseholdMessages;

    private const string UUID_V7_REGEX
        = '[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}';

    /**
     * Default page size used by the Transaction History card when the
     * client does not specify one. Matched by the initial lazy-load
     * trigger emitted from {@see _card_history.html.twig}.
     */
    private const int HISTORY_DEFAULT_PAGE_SIZE = 20;

    /**
     * Hard cap on the Transaction History page size to keep a single
     * request bounded. Out-of-range values produce HTTP 400.
     */
    private const int HISTORY_MAX_PAGE_SIZE = 50;

    private const string MEMBER_NOT_FOUND_MESSAGE = 'Member not found.';

    private const string TEMPLATE_PROFILE_EDIT = 'households/detail/_card_profile_edit.html.twig';

    private const string TEMPLATE_ADDRESS_EDIT = 'households/detail/_address_sub_card_edit.html.twig';

    private const string TEMPLATE_RESIDENCY_EDIT = 'households/detail/_residency_sub_card_edit.html.twig';

    private const string TEMPLATE_CONTACT_EDIT = 'households/detail/_contact_sub_card_edit.html.twig';

    /**
     * HTMX response header (LRA-153). Success responses set it so assets/app.js
     * can announce the outcome of an in-place update in the shared #lr-live
     * region without a focus change.
     */
    private const string HEADER_HX_TRIGGER = 'HX-Trigger';

    private const string HX_TRIGGER_PROFILE_SAVED = 'profileSaved';

    private const string HX_TRIGGER_CONTACT_SAVED = 'contactSaved';

    private const string HX_TRIGGER_MEMBER_LOADED = 'memberLoaded';

    public function __construct(
        private readonly MessageBusInterface $queryBus,
        private readonly MessageBusInterface $commandBus,
        private readonly ClockInterface $clock,
        private readonly MemberTransactionHistory $transactionHistory,
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

    #[Route(
        '/admin/users/{householdId}/{memberId}',
        name: 'member_detail',
        requirements: [
            'householdId' => self::UUID_V7_REGEX,
            'memberId'    => self::UUID_V7_REGEX,
        ],
        methods: ['GET'],
    )]
    public function __invoke(string $householdId, string $memberId): Response
    {
        try {
            $detail = $this->runQuery($householdId, $memberId);
        } catch (MemberNotFound | HouseholdNotFound | InvalidHouseholdId | InvalidMemberId) {
            throw $this->createNotFoundException(self::MEMBER_NOT_FOUND_MESSAGE);
        }

        return $this->render('households/detail.html.twig', [
            'detail' => $detail,
        ]);
    }

    /**
     * HTMX partial endpoint that returns the three lower cards
     * (Profile, Address, History) for the requested member, plus
     * out-of-band copies of everything else on the page that shows the
     * active member's identity (LRA-203): the document title, the shared
     * page head, the member header, and the Household card's roster
     * highlight. The Household card (LRA-42) wraps this endpoint to switch
     * the active member without re-rendering itself; HTMX swaps
     * `#member-cards-lower` with the response body, applies the OOB
     * fragments to their own ids, and the client-side `hx-push-url` keeps
     * the browser URL in sync with the new (householdId, memberId) tuple.
     * The Profile/Address/Residency cards' Cancel buttons also call this
     * endpoint for the currently active member; they receive the same OOB
     * copies, which is harmless since nothing has changed.
     *
     * Uses an underscore-prefixed path segment to keep the partial route
     * out of the canonical user-facing URL space served by the main
     * member-detail route — same controller, same query, different view
     * shape.
     */
    #[Route(
        '/admin/users/{householdId}/{memberId}/_lower-cards',
        name: 'member_detail_lower_cards',
        requirements: [
            'householdId' => self::UUID_V7_REGEX,
            'memberId'    => self::UUID_V7_REGEX,
        ],
        methods: ['GET'],
    )]
    public function lowerCards(string $householdId, string $memberId): Response
    {
        try {
            $detail = $this->runQuery($householdId, $memberId);
        } catch (MemberNotFound | HouseholdNotFound | InvalidHouseholdId | InvalidMemberId) {
            throw $this->createNotFoundException(self::MEMBER_NOT_FOUND_MESSAGE);
        }

        $response = $this->render('households/detail/_lower_cards.html.twig', [
            'detail' => $detail,
            'oob'    => true,
        ]);
        $response->headers->set(self::HEADER_HX_TRIGGER, $this->memberLoadedTrigger($detail));

        return $response;
    }

    /**
     * Builds the HX-Trigger payload that names the freshly loaded member so
     * the shared #lr-live region can announce the context switch (LRA-153).
     */
    private function memberLoadedTrigger(MemberDetail $detail): string
    {
        $name = $detail->profile->fullName;

        return (string) json_encode(
            [self::HX_TRIGGER_MEMBER_LOADED => ['name' => $name]],
            JSON_THROW_ON_ERROR,
        );
    }

    /**
     * HTMX partial endpoint that returns the Profile card edit-mode body,
     * pre-populated with the current profile values. The card's "Edit"
     * button swaps `#card-profile-body` with this response; submission of
     * the returned form posts to {@see self::submitProfile()}.
     */
    #[Route(
        '/admin/users/{householdId}/{memberId}/profile/edit',
        name: 'member_profile_edit_form',
        requirements: [
            'householdId' => self::UUID_V7_REGEX,
            'memberId'    => self::UUID_V7_REGEX,
        ],
        methods: ['GET'],
    )]
    public function editForm(string $householdId, string $memberId): Response
    {
        try {
            $detail = $this->runQuery($householdId, $memberId);
        } catch (MemberNotFound | HouseholdNotFound | InvalidHouseholdId | InvalidMemberId) {
            throw $this->createNotFoundException(self::MEMBER_NOT_FOUND_MESSAGE);
        }

        $input = $this->inputFromProfile($detail);
        $form = $this->createForm(UpdateMemberProfileFormType::class, $input);

        return $this->render(self::TEMPLATE_PROFILE_EDIT, [
            'form' => $form->createView(),
            'householdId' => $householdId,
            'memberId' => $memberId,
        ]);
    }

    /**
     * Handles the Profile card edit submission. On validation failure or a
     * domain exception re-renders the edit partial at HTTP 422 with inline
     * form errors. On success re-dispatches {@see \App\Households\Application\Query\GetMemberDetail} and
     * returns the read-mode partial at HTTP 200, so the card swaps back to
     * read mode with the updated values.
     */
    #[Route(
        '/admin/users/{householdId}/{memberId}/profile',
        name: 'member_profile_submit',
        requirements: [
            'householdId' => self::UUID_V7_REGEX,
            'memberId'    => self::UUID_V7_REGEX,
        ],
        methods: ['POST'],
    )]
    public function submitProfile(string $householdId, string $memberId, Request $request): Response
    {
        $input = new UpdateMemberProfileInput();
        $form = $this->createForm(UpdateMemberProfileFormType::class, $input);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $response = $this->tryUpdateProfile($householdId, $memberId, $input, $form);
            if ($response !== null) {
                return $response;
            }
        }

        return $this->renderEditPartial(
            self::TEMPLATE_PROFILE_EDIT,
            $form,
            $householdId,
            $memberId,
            Response::HTTP_UNPROCESSABLE_ENTITY,
        );
    }

    /**
     * Dispatches {@see UpdateMemberProfile} for a submitted-and-valid
     * profile form and returns the read-mode response on success. On a
     * domain validation failure it writes the error onto $form and returns
     * null so {@see self::submitProfile()} falls through to the 422
     * edit-partial re-render; on a not-found condition it throws directly.
     *
     * Extracted from {@see self::submitProfile()} to keep that method's
     * cognitive complexity under the SonarCloud php:S3776 threshold now
     * that the form covers name, DOB, gender, nickname, salutation,
     * height, and weight (LRA-205).
     *
     * @template TData
     *
     * @param FormInterface<TData> $form
     */
    private function tryUpdateProfile(
        string $householdId,
        string $memberId,
        UpdateMemberProfileInput $input,
        FormInterface $form,
    ): ?Response {
        try {
            $this->dispatchCommandUnwrapping(new UpdateMemberProfile(
                householdId: $householdId,
                memberId: $memberId,
                firstName: (string) $input->firstName,
                lastName: (string) $input->lastName,
                middleName: $input->middleName,
                suffix: $input->suffix,
                dobIso: (string) $input->dobIso,
                genderCode: (string) $input->genderCode,
                nickname: $input->nickname,
                salutationCode: $input->salutationCode,
                heightInches: $input->heightInches,
                weightPounds: $input->weightPounds,
            ));

            // Re-load the projection so the swapped read partial reflects
            // the freshly persisted values; an unchanged submit yields the
            // same projection and the card still swaps back to read mode.
            $response = $this->render('households/detail/_card_profile_read.html.twig', [
                'detail' => $this->runQuery($householdId, $memberId),
            ]);
            $response->headers->set(self::HEADER_HX_TRIGGER, self::HX_TRIGGER_PROFILE_SAVED);

            return $response;
        } catch (MemberNotFound | HouseholdNotFound | InvalidHouseholdId | InvalidMemberId) {
            throw $this->createNotFoundException(self::MEMBER_NOT_FOUND_MESSAGE);
        } catch (InvalidPersonName $exception) {
            $this->applyNameErrorToForm($form, $exception);
        } catch (InvalidDateOfBirth $exception) {
            $this->applyDobErrorToForm($form, $exception);
        } catch (InvalidHeight $exception) {
            $form->get('heightInches')->addError(new FormError($exception->getMessage()));
        } catch (InvalidWeight $exception) {
            $form->get('weightPounds')->addError(new FormError($exception->getMessage()));
        } catch (SharedDomainException $exception) {
            $form->addError(new FormError($exception->getMessage()));
        }

        return null;
    }

    /**
     * Maps an InvalidDateOfBirth exception onto the dobIso field when
     * present, otherwise onto the form root.
     *
     * @template TData
     *
     * @param FormInterface<TData> $form
     */
    private function applyDobErrorToForm(FormInterface $form, InvalidDateOfBirth $exception): void
    {
        if ($form->has('dobIso')) {
            $form->get('dobIso')->addError(new FormError($exception->getMessage()));

            return;
        }

        $form->addError(new FormError($exception->getMessage()));
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
        try {
            $detail = $this->runQuery($householdId, $memberId);
        } catch (MemberNotFound | HouseholdNotFound | InvalidHouseholdId | InvalidMemberId) {
            throw $this->createNotFoundException(self::MEMBER_NOT_FOUND_MESSAGE);
        }

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
        try {
            $detail = $this->runQuery($householdId, $memberId);
        } catch (MemberNotFound | HouseholdNotFound | InvalidHouseholdId | InvalidMemberId) {
            throw $this->createNotFoundException(self::MEMBER_NOT_FOUND_MESSAGE);
        }

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
     * HTMX partial endpoint that returns the Residency sub-card change
     * form, pre-populated with the member's current status. The Change
     * Residency button swaps `#residency-sub-card-body` with this
     * response; submission posts to {@see self::submitResidency()}.
     */
    #[Route(
        '/admin/users/{householdId}/{memberId}/residency/edit',
        name: 'member_residency_edit_form',
        requirements: [
            'householdId' => self::UUID_V7_REGEX,
            'memberId'    => self::UUID_V7_REGEX,
        ],
        methods: ['GET'],
    )]
    public function editResidencyForm(string $householdId, string $memberId): Response
    {
        try {
            $detail = $this->runQuery($householdId, $memberId);
        } catch (MemberNotFound | HouseholdNotFound | InvalidHouseholdId | InvalidMemberId) {
            throw $this->createNotFoundException(self::MEMBER_NOT_FOUND_MESSAGE);
        }

        $input = $this->inputFromResidency($detail);
        $form = $this->createForm(ChangeMemberResidencyFormType::class, $input);

        return $this->render(self::TEMPLATE_RESIDENCY_EDIT, [
            'form' => $form->createView(),
            'householdId' => $householdId,
            'memberId' => $memberId,
        ]);
    }

    /**
     * Handles the Residency sub-card change submission. On validation
     * failure or a domain exception re-renders the edit partial at
     * HTTP 422 with inline form errors. On success re-dispatches the read
     * query and returns the residency sub-card read partial at HTTP 200,
     * swapping the sub-card back to read mode with the new status.
     */
    #[Route(
        '/admin/users/{householdId}/{memberId}/residency',
        name: 'member_residency_submit',
        requirements: [
            'householdId' => self::UUID_V7_REGEX,
            'memberId'    => self::UUID_V7_REGEX,
        ],
        methods: ['POST'],
    )]
    public function submitResidency(string $householdId, string $memberId, Request $request): Response
    {
        $input = new ChangeMemberResidencyInput();
        $form = $this->createForm(ChangeMemberResidencyFormType::class, $input);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->renderEditPartial(
                self::TEMPLATE_RESIDENCY_EDIT,
                $form,
                $householdId,
                $memberId,
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $command = new ChangeMemberResidency(
            householdId: $householdId,
            memberId: $memberId,
            residencyStatusCode: (string) $input->residencyStatusCode,
            effectiveFromIso: (string) $input->effectiveFromIso,
            reason: $input->reason !== null && $input->reason !== '' ? $input->reason : null,
        );

        try {
            $this->dispatchCommandUnwrapping($command);
        } catch (MemberNotFound | HouseholdNotFound | InvalidHouseholdId | InvalidMemberId) {
            throw $this->createNotFoundException(self::MEMBER_NOT_FOUND_MESSAGE);
        } catch (SharedDomainException $exception) {
            $form->addError(new FormError($exception->getMessage()));

            return $this->renderEditPartial(
                self::TEMPLATE_RESIDENCY_EDIT,
                $form,
                $householdId,
                $memberId,
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        try {
            $detail = $this->runQuery($householdId, $memberId);
        } catch (MemberNotFound | HouseholdNotFound | InvalidHouseholdId | InvalidMemberId) {
            throw $this->createNotFoundException(self::MEMBER_NOT_FOUND_MESSAGE);
        }

        return $this->render('households/detail/_residency_sub_card_read.html.twig', [
            'detail' => $detail,
        ]);
    }

    /**
     * HTMX fragment endpoint that returns a single page of the member's
     * transaction history (LRA-45). The History card body lazy-loads
     * page 1 the first time the user expands the card; the "Load more"
     * button in the response triggers subsequent pages, each one
     * appended into the table via HTMX's `outerHTML` swap on the
     * button's `<tr>` placeholder.
     *
     * Page-size validation is enforced at the boundary: out-of-range
     * values return HTTP 400 rather than being silently clamped, so a
     * caller that asks for too much is told so instead of getting a
     * smaller answer than requested.
     *
     * The existence of the (household, member) pair is not pre-checked
     * here: the stub adapter naturally returns an empty page for an
     * unknown member, and the cost of an extra detail query per
     * scrolled page is wasteful. When a real Transactions ACL adapter
     * lands, it owns the unknown-member behaviour for its own backend.
     */
    #[Route(
        '/admin/users/{householdId}/{memberId}/history',
        name: 'member_history_page',
        requirements: [
            'householdId' => self::UUID_V7_REGEX,
            'memberId'    => self::UUID_V7_REGEX,
        ],
        methods: ['GET'],
    )]
    public function historyPage(string $householdId, string $memberId, Request $request): Response
    {
        $page = $request->query->getInt('page', 1);
        $pageSize = $request->query->getInt('pageSize', self::HISTORY_DEFAULT_PAGE_SIZE);

        if ($page < 1) {
            throw new BadRequestHttpException(
                sprintf('page must be >= 1, got %d.', $page),
            );
        }
        if ($pageSize < 1 || $pageSize > self::HISTORY_MAX_PAGE_SIZE) {
            throw new BadRequestHttpException(
                sprintf(
                    'pageSize must be between 1 and %d, got %d.',
                    self::HISTORY_MAX_PAGE_SIZE,
                    $pageSize,
                ),
            );
        }

        try {
            $householdIdVo = HouseholdIdVo::fromString($householdId);
            $memberIdVo = MemberIdVo::fromString($memberId);
        } catch (InvalidHouseholdId | InvalidMemberId) {
            // Route requirements already enforce UUID v7, so this is
            // defence in depth — surface as 404 to match the rest of
            // the member-scoped endpoints.
            throw $this->createNotFoundException(self::MEMBER_NOT_FOUND_MESSAGE);
        }

        $pageDto = $this->transactionHistory->page(
            $householdIdVo,
            $memberIdVo,
            $page,
            $pageSize,
        );

        return $this->render('households/detail/_card_history_rows.html.twig', [
            'householdId' => $householdId,
            'memberId' => $memberId,
            'page' => $pageDto,
        ]);
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

    private function inputFromResidency(MemberDetail $detail): ChangeMemberResidencyInput
    {
        $input = new ChangeMemberResidencyInput();
        $input->residencyStatusCode = $detail->residency->status;
        // Default the effective-from field to "today" as seen by the
        // application clock (whatever timezone the injected ClockInterface
        // surfaces — typically the configured app timezone, not UTC). The
        // pre-fill is purely a UX nicety; the command handler re-parses
        // the value.
        $input->effectiveFromIso = $this->clock->now()->format('Y-m-d');
        $input->reason = null;

        return $input;
    }

    private function inputFromProfile(MemberDetail $detail): UpdateMemberProfileInput
    {
        $input = new UpdateMemberProfileInput();
        $input->firstName = $detail->profile->firstName;
        $input->middleName = $detail->profile->middleName;
        $input->lastName = $detail->profile->lastName;
        $input->suffix = $detail->profile->suffix;
        $input->dobIso = $detail->profile->dobIso;
        $input->genderCode = $detail->profile->genderCode;
        $input->nickname = $detail->profile->nickname;
        $input->salutationCode = $detail->profile->salutationCode;
        $input->heightInches = $detail->profile->heightInches;
        $input->weightPounds = $detail->profile->weightPounds;

        return $input;
    }

    private function inputFromContact(MemberDetail $detail): UpdateMemberContactInput
    {
        $input = new UpdateMemberContactInput();
        $input->email = $detail->profile->email;
        $input->phone = $detail->profile->phone;

        return $input;
    }

    /**
     * Re-renders a card's edit-mode partial at the given HTTP status with the
     * form (and its errors) intact. The three card edit partials share an
     * identical shape — form view plus the (householdId, memberId) tuple the
     * template needs for its action URLs — so they differ only by template.
     *
     * @template TData
     *
     * @param FormInterface<TData> $form
     */
    private function renderEditPartial(
        string $template,
        FormInterface $form,
        string $householdId,
        string $memberId,
        int $status,
    ): Response {
        return $this->render(
            $template,
            [
                'form' => $form->createView(),
                'householdId' => $householdId,
                'memberId' => $memberId,
            ],
            new Response(null, $status),
        );
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
