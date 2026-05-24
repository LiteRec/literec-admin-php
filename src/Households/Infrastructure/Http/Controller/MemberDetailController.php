<?php

declare(strict_types=1);

namespace App\Households\Infrastructure\Http\Controller;

use App\Households\Application\Command\UpdateMemberProfile;
use App\Households\Application\Query\GetMemberDetail;
use App\Households\Application\Query\Port\MemberDetail;
use App\Households\Domain\Exception\HouseholdNotFound;
use App\Households\Domain\Exception\HouseholdsDomainException;
use App\Households\Domain\Exception\InvalidDateOfBirth;
use App\Households\Domain\Exception\InvalidHouseholdId;
use App\Households\Domain\Exception\InvalidMemberId;
use App\Households\Domain\Exception\InvalidPersonName;
use App\Households\Domain\Exception\MemberNotFound;
use App\Households\Infrastructure\Http\Form\UpdateMemberProfileFormType;
use App\Households\Infrastructure\Http\Form\UpdateMemberProfileInput;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
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

    public function __construct(
        private readonly MessageBusInterface $queryBus,
        private readonly MessageBusInterface $commandBus,
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
            throw $this->createNotFoundException('Member not found.');
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
            throw $this->createNotFoundException('Member not found.');
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
            throw $this->createNotFoundException('Member not found.');
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
            throw $this->createNotFoundException('Member not found.');
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
        } catch (HouseholdsDomainException $exception) {
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
            throw $this->createNotFoundException('Member not found.');
        }

        return $this->render('households/detail/_card_profile_read.html.twig', [
            'detail' => $detail,
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
