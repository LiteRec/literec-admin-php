<?php

declare(strict_types=1);

namespace App\Households\Infrastructure\Http\Controller;

use App\Households\Application\Command\ChangeMemberResidency;
use App\Households\Application\Command\UpdateHouseholdAddress;
use App\Households\Application\Command\UpdateMemberProfile;
use App\Households\Application\Port\MemberTransactionHistory;
use App\Households\Application\Query\GetMemberDetail;
use App\Households\Application\Query\Port\MemberDetail;
use App\Households\Domain\Exception\HouseholdNotFound;
use App\Households\Domain\Exception\InvalidAddress;
use App\Households\Domain\Exception\InvalidDateOfBirth;
use App\Households\Domain\Exception\InvalidHouseholdId;
use App\Households\Domain\Exception\InvalidMemberId;
use App\Households\Domain\Exception\InvalidPersonName;
use App\Households\Domain\Exception\MemberNotFound;
use App\Households\Domain\ValueObject\HouseholdId as HouseholdIdVo;
use App\Households\Domain\ValueObject\MemberId as MemberIdVo;
use App\Households\Infrastructure\Http\Form\ChangeMemberResidencyFormType;
use App\Households\Infrastructure\Http\Form\ChangeMemberResidencyInput;
use App\Households\Infrastructure\Http\Form\UpdateHouseholdAddressFormType;
use App\Households\Infrastructure\Http\Form\UpdateHouseholdAddressInput;
use App\Households\Infrastructure\Http\Form\UpdateMemberProfileFormType;
use App\Households\Infrastructure\Http\Form\UpdateMemberProfileInput;
use App\Shared\Domain\Exception\SharedDomainException;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

/**
 * HTTP adapter for the member detail page (LRA-41) and the in-card
 * Profile read/edit flow (LRA-43).
 *
 * Renders the composite shell that hosts the four card slots — Household
 * (LRA-42), Profile (LRA-43), Address & Residency (LRA-44), Transaction
 * History (LRA-45). The Profile card mutation flow lives entirely in this
 * controller: an HTMX-swapped read partial, an HTMX-swapped edit form,
 * and a POST that re-dispatches the read query and returns the read
 * partial on success.
 *
 * The controller stays thin: dispatches {@see GetMemberDetail} via the
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

    public function __construct(
        private readonly MessageBusInterface $queryBus,
        private readonly MessageBusInterface $commandBus,
        private readonly ClockInterface $clock,
        private readonly MemberTransactionHistory $transactionHistory,
    ) {
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
     * HTMX partial endpoint that returns only the three lower cards
     * (Profile, Address, History) for the requested member. The Household
     * card (LRA-42) wraps this endpoint to switch the active member
     * without re-rendering itself; HTMX swaps `#member-cards-lower` with
     * the response body and the client-side `hx-push-url` keeps the
     * browser URL in sync with the new (householdId, memberId) tuple.
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

        return $this->render('households/detail/_lower_cards.html.twig', [
            'detail' => $detail,
        ]);
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

        return $this->render('households/detail/_card_profile_edit.html.twig', [
            'form' => $form->createView(),
            'householdId' => $householdId,
            'memberId' => $memberId,
        ]);
    }

    /**
     * Handles the Profile card edit submission. On validation failure or a
     * domain exception re-renders the edit partial at HTTP 422 with inline
     * form errors. On success re-dispatches {@see GetMemberDetail} and
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

        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->renderEditPartial($form, $householdId, $memberId, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $command = new UpdateMemberProfile(
            householdId: $householdId,
            memberId: $memberId,
            firstName: (string) $input->firstName,
            lastName: (string) $input->lastName,
            middleName: $input->middleName,
            suffix: $input->suffix,
            dobIso: (string) $input->dobIso,
            genderCode: (string) $input->genderCode,
        );

        try {
            $this->dispatchCommandUnwrapping($command);
        } catch (MemberNotFound | HouseholdNotFound | InvalidHouseholdId | InvalidMemberId) {
            throw $this->createNotFoundException(self::MEMBER_NOT_FOUND_MESSAGE);
        } catch (InvalidPersonName $exception) {
            $this->applyNameErrorToForm($form, $exception);

            return $this->renderEditPartial($form, $householdId, $memberId, Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (InvalidDateOfBirth $exception) {
            if ($form->has('dobIso')) {
                $form->get('dobIso')->addError(new FormError($exception->getMessage()));
            } else {
                $form->addError(new FormError($exception->getMessage()));
            }

            return $this->renderEditPartial($form, $householdId, $memberId, Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (SharedDomainException $exception) {
            $form->addError(new FormError($exception->getMessage()));

            return $this->renderEditPartial($form, $householdId, $memberId, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Re-load the projection so the swapped read partial reflects the
        // freshly persisted values. The aggregate's no-op guard means an
        // unchanged submit yields the same projection; the card still swaps
        // back to read mode either way.
        try {
            $detail = $this->runQuery($householdId, $memberId);
        } catch (MemberNotFound | HouseholdNotFound | InvalidHouseholdId | InvalidMemberId) {
            throw $this->createNotFoundException(self::MEMBER_NOT_FOUND_MESSAGE);
        }

        return $this->render('households/detail/_card_profile_read.html.twig', [
            'detail' => $detail,
        ]);
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

        return $this->render('households/detail/_address_sub_card_edit.html.twig', [
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

        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->renderAddressEditPartial($form, $householdId, $memberId, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $command = new UpdateHouseholdAddress(
            householdId: $householdId,
            street: (string) $input->street,
            unit: $input->unit,
            city: (string) $input->city,
            state: (string) $input->state,
            postalCode: (string) $input->postalCode,
            country: (string) $input->country,
        );

        try {
            $this->dispatchCommandUnwrapping($command);
        } catch (HouseholdNotFound | InvalidHouseholdId) {
            throw $this->createNotFoundException('Household not found.');
        } catch (InvalidAddress $exception) {
            $this->applyAddressErrorToForm($form, $exception);

            return $this->renderAddressEditPartial($form, $householdId, $memberId, Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (SharedDomainException $exception) {
            $form->addError(new FormError($exception->getMessage()));

            return $this->renderAddressEditPartial($form, $householdId, $memberId, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $detail = $this->runQuery($householdId, $memberId);
        } catch (MemberNotFound | HouseholdNotFound | InvalidHouseholdId | InvalidMemberId) {
            throw $this->createNotFoundException(self::MEMBER_NOT_FOUND_MESSAGE);
        }

        return $this->render('households/detail/_address_sub_card_read.html.twig', [
            'detail' => $detail,
        ]);
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

        return $this->render('households/detail/_residency_sub_card_edit.html.twig', [
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
            return $this->renderResidencyEditPartial(
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

            return $this->renderResidencyEditPartial(
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
     * Dispatches the GetMemberDetail query and unwraps Messenger's
     * HandlerFailedException so the original domain exceptions reach the
     * caller. Domain exceptions cannot leak through Messenger's bus in
     * their raw form because handler failures are always wrapped.
     */
    private function runQuery(string $householdId, string $memberId): MemberDetail
    {
        try {
            $envelope = $this->queryBus->dispatch(new GetMemberDetail($householdId, $memberId));
        } catch (HandlerFailedException $wrapper) {
            $nested = $wrapper->getPrevious();
            if ($nested instanceof Throwable) {
                throw $nested;
            }
            throw $wrapper;
        }

        $result = $this->resultOf($envelope);

        if (!$result instanceof MemberDetail) {
            throw new \LogicException(sprintf(
                'GetMemberDetail handler returned %s, expected %s.',
                get_debug_type($result),
                MemberDetail::class,
            ));
        }

        return $result;
    }

    /**
     * Dispatch a command through the command bus and unwrap
     * HandlerFailedException so domain exceptions surface to the caller.
     */
    private function dispatchCommandUnwrapping(object $command): void
    {
        try {
            $this->commandBus->dispatch($command);
        } catch (HandlerFailedException $wrapper) {
            $nested = $wrapper->getPrevious();
            if ($nested instanceof Throwable) {
                throw $nested;
            }
            throw $wrapper;
        }
    }

    /**
     * Extract the single handler result from a dispatched Envelope. Mirrors
     * Messenger's HandleTrait behaviour without coupling the controller to
     * the trait (the controller uses both the query bus and the command
     * bus, which the trait cannot multiplex).
     */
    private function resultOf(Envelope $envelope): mixed
    {
        $stamps = $envelope->all(HandledStamp::class);

        if ($stamps === []) {
            throw new \LogicException('Dispatched message produced no HandledStamp.');
        }

        if (count($stamps) > 1) {
            throw new \LogicException('Dispatched message produced more than one HandledStamp.');
        }

        /** @var HandledStamp $stamp */
        $stamp = $stamps[0];

        return $stamp->getResult();
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

        return $input;
    }

    /**
     * @template TData
     * @param FormInterface<TData> $form
     */
    private function renderEditPartial(
        FormInterface $form,
        string $householdId,
        string $memberId,
        int $status,
    ): Response {
        return $this->render(
            'households/detail/_card_profile_edit.html.twig',
            [
                'form' => $form->createView(),
                'householdId' => $householdId,
                'memberId' => $memberId,
            ],
            new Response(null, $status),
        );
    }

    /**
     * @template TData
     * @param FormInterface<TData> $form
     */
    private function renderAddressEditPartial(
        FormInterface $form,
        string $householdId,
        string $memberId,
        int $status,
    ): Response {
        return $this->render(
            'households/detail/_address_sub_card_edit.html.twig',
            [
                'form' => $form->createView(),
                'householdId' => $householdId,
                'memberId' => $memberId,
            ],
            new Response(null, $status),
        );
    }

    /**
     * @template TData
     * @param FormInterface<TData> $form
     */
    private function renderResidencyEditPartial(
        FormInterface $form,
        string $householdId,
        string $memberId,
        int $status,
    ): Response {
        return $this->render(
            'households/detail/_residency_sub_card_edit.html.twig',
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

    /**
     * Maps an InvalidPersonName exception onto the appropriate name field
     * if one is identifiable, otherwise onto the form root. The exception
     * message is the only signal available — by convention it mentions
     * "first name" or "last name" — so the mapping stays heuristic and
     * safely falls back to a form-level error.
     *
     * @template TData
     * @param FormInterface<TData> $form
     */
    private function applyNameErrorToForm(FormInterface $form, InvalidPersonName $exception): void
    {
        $message = $exception->getMessage();
        $lower = strtolower($message);

        if (str_contains($lower, 'first name') && $form->has('firstName')) {
            $form->get('firstName')->addError(new FormError($message));

            return;
        }

        if (str_contains($lower, 'last name') && $form->has('lastName')) {
            $form->get('lastName')->addError(new FormError($message));

            return;
        }

        $form->addError(new FormError($message));
    }
}
