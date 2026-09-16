<?php

declare(strict_types=1);

namespace App\Households\Infrastructure\Http\Controller;

use App\Households\Application\Command\DeactivateMember;
use App\Households\Application\Command\ReactivateMember;
use App\Households\Domain\Exception\HouseholdNotFound;
use App\Households\Domain\Exception\InvalidHouseholdId;
use App\Households\Domain\Exception\InvalidMemberId;
use App\Households\Domain\Exception\MemberAlreadyMerged;
use App\Households\Domain\Exception\MemberNotFound;
use App\Households\Infrastructure\Http\Form\DeactivateMemberFormType;
use App\Households\Infrastructure\Http\Form\DeactivateMemberInput;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * HTTP adapter for the member activation-state transitions (LRA-211):
 * deactivating an active member via a confirmation dialog that requires a
 * reason, and reactivating a deactivated one. A separate controller from
 * {@see MemberDetailController} — the page shell and its four per-card
 * controllers (LRA-235) — because activation state is its own reason to
 * change, distinct from any single card.
 *
 * Both success paths return an `HX-Redirect` to the member detail page
 * rather than an in-place swap: the Active/Deactivated badge lives in the
 * page header and the Household roster marks deactivated members outside
 * the `#member-cards-lower` swap region, so a full navigation refreshes
 * header, roster, and Profile card consistently in one response.
 *
 * No `#[IsGranted]`: no Households endpoint carries one today (the
 * firewall is ROLE_USER only); a `manage_households` voter is out of scope
 * here.
 */
final class MemberLifecycleController extends AbstractController
{
    use DispatchesHouseholdMessages;
    use RedirectsToMemberDetail;
    use RendersMemberCardPartials;

    private const string TEMPLATE_DEACTIVATE_DIALOG = 'households/detail/_deactivate_dialog.html.twig';

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
     * Renders the Deactivate Member confirmation dialog, pre-populated
     * with the member's name for the confirmation copy. The Profile
     * card's Deactivate button swaps it into the DOM via `beforeend`.
     */
    #[Route(
        '/admin/users/{householdId}/{memberId}/deactivate',
        name: 'member_deactivate_form',
        requirements: [
            'householdId' => self::UUID_V7_REGEX,
            'memberId'    => self::UUID_V7_REGEX,
        ],
        methods: ['GET'],
    )]
    public function deactivateForm(string $householdId, string $memberId): Response
    {
        $detail = $this->findDetailOrFail($householdId, $memberId);

        $form = $this->createForm(DeactivateMemberFormType::class, new DeactivateMemberInput());

        return $this->render(self::TEMPLATE_DEACTIVATE_DIALOG, [
            'form' => $form->createView(),
            'householdId' => $householdId,
            'memberId' => $memberId,
            'memberName' => $detail->profile->fullName,
        ]);
    }

    /**
     * Handles the Deactivate Member dialog submission. A blank reason (or
     * a missing/invalid CSRF token) re-renders the dialog at HTTP 422 with
     * an inline error; success redirects to the member detail page.
     */
    #[Route(
        '/admin/users/{householdId}/{memberId}/deactivate',
        name: 'member_deactivate_submit',
        requirements: [
            'householdId' => self::UUID_V7_REGEX,
            'memberId'    => self::UUID_V7_REGEX,
        ],
        methods: ['POST'],
    )]
    public function deactivateSubmit(string $householdId, string $memberId, Request $request): Response
    {
        $input = new DeactivateMemberInput();
        $form = $this->createForm(DeactivateMemberFormType::class, $input);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->dispatchCommandUnwrapping(new DeactivateMember(
                    householdId: $householdId,
                    memberId: $memberId,
                    reason: (string) $input->reason,
                ));

                return $this->hxRedirectToMemberDetail($householdId, $memberId);
            } catch (MemberNotFound | HouseholdNotFound | InvalidHouseholdId | InvalidMemberId) {
                throw $this->createNotFoundException(self::MEMBER_NOT_FOUND_MESSAGE);
            } catch (MemberAlreadyMerged $exception) {
                // The Profile card only hides the Deactivate button for a
                // merged member (_card_profile_read.html.twig); it does not
                // stop a dialog opened before a concurrent merge from being
                // submitted. Surface it as a form error like
                // MergeMembersController::submit() does for the same
                // exception, rather than letting it escape as a 500.
                $form->addError(new FormError($exception->getMessage()));
            }
        }

        $detail = $this->findDetailOrFail($householdId, $memberId);

        return $this->render(
            self::TEMPLATE_DEACTIVATE_DIALOG,
            [
                'form' => $form->createView(),
                'householdId' => $householdId,
                'memberId' => $memberId,
                'memberName' => $detail->profile->fullName,
            ],
            new Response(null, Response::HTTP_UNPROCESSABLE_ENTITY),
        );
    }

    /**
     * Reactivates a deactivated member. Form-less: the Profile card's
     * Reactivate button posts a bare CSRF-protected form, so the token is
     * read directly off the request payload rather than through a Symfony
     * FormType. An invalid or missing token is rejected as HTTP 403 rather
     * than 422 since there is no form to re-render.
     */
    #[Route(
        '/admin/users/{householdId}/{memberId}/reactivate',
        name: 'member_reactivate',
        requirements: [
            'householdId' => self::UUID_V7_REGEX,
            'memberId'    => self::UUID_V7_REGEX,
        ],
        methods: ['POST'],
    )]
    public function reactivate(string $householdId, string $memberId, Request $request): Response
    {
        $token = $request->getPayload()->getString('_token');
        if (!$this->isCsrfTokenValid('reactivate_member', $token)) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        try {
            $this->dispatchCommandUnwrapping(new ReactivateMember(
                householdId: $householdId,
                memberId: $memberId,
            ));
        } catch (MemberNotFound | HouseholdNotFound | InvalidHouseholdId | InvalidMemberId) {
            throw $this->createNotFoundException(self::MEMBER_NOT_FOUND_MESSAGE);
        } catch (MemberAlreadyMerged $exception) {
            // No form to re-render here (see deactivateSubmit()'s catch for
            // the same exception) — surface as 409 so the client sees a
            // real conflict instead of a 500.
            throw new ConflictHttpException($exception->getMessage(), $exception);
        }

        return $this->hxRedirectToMemberDetail($householdId, $memberId);
    }
}
