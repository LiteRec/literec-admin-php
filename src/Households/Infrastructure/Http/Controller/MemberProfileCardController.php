<?php

declare(strict_types=1);

namespace App\Households\Infrastructure\Http\Controller;

use App\Households\Application\Command\UpdateMemberProfile;
use App\Households\Application\Query\Port\MemberDetail;
use App\Households\Domain\Exception\HouseholdNotFound;
use App\Households\Domain\Exception\InvalidDateOfBirth;
use App\Households\Domain\Exception\InvalidHeight;
use App\Households\Domain\Exception\InvalidHouseholdId;
use App\Households\Domain\Exception\InvalidMemberId;
use App\Households\Domain\Exception\InvalidPersonName;
use App\Households\Domain\Exception\InvalidWeight;
use App\Households\Domain\Exception\MemberNotFound;
use App\Households\Infrastructure\Http\Form\AppliesPersonNameErrors;
use App\Households\Infrastructure\Http\Form\UpdateMemberProfileFormType;
use App\Households\Infrastructure\Http\Form\UpdateMemberProfileInput;
use App\Shared\Domain\Exception\SharedDomainException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * HTTP adapter for the member detail page's Profile card edit flow
 * (LRA-43): an HTMX-swapped edit form and a POST that re-dispatches the
 * read query and returns the read partial on success. Split out of
 * {@see MemberDetailController} (LRA-235) — the Profile card is one of
 * four independently editable cards on that page, each a separate reason
 * to change.
 *
 * The controller stays thin: dispatches {@see \App\Households\Application\Query\GetMemberDetail} via the
 * `query.bus` and {@see UpdateMemberProfile} via the `command.bus`, catches
 * the domain exceptions that bubble out of either bus, and translates
 * them to HTTP status codes (404 for missing aggregates, 422 with inline
 * form errors for validation failures).
 */
final class MemberProfileCardController extends AbstractController
{
    use AppliesPersonNameErrors;
    use DispatchesHouseholdMessages;
    use RendersMemberCardPartials;

    private const string TEMPLATE_PROFILE_EDIT = 'households/detail/_card_profile_edit.html.twig';

    /**
     * HTMX response header (LRA-153). Success responses set it so
     * assets/app.js can announce the outcome of an in-place update in the
     * shared #lr-live region without a focus change.
     */
    private const string HEADER_HX_TRIGGER = 'HX-Trigger';

    private const string HX_TRIGGER_PROFILE_SAVED = 'profileSaved';

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
        $detail = $this->findDetailOrFail($householdId, $memberId);

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
}
