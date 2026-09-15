<?php

declare(strict_types=1);

namespace App\Households\Infrastructure\Http\Controller;

use App\Households\Application\Command\DeactivateMember;
use App\Households\Application\Command\ReactivateMember;
use App\Households\Application\Query\Port\MemberDetail;
use App\Households\Domain\Exception\HouseholdNotFound;
use App\Households\Domain\Exception\InvalidHouseholdId;
use App\Households\Domain\Exception\InvalidMemberId;
use App\Households\Domain\Exception\MemberNotFound;
use App\Households\Infrastructure\Http\Form\DeactivateMemberFormType;
use App\Households\Infrastructure\Http\Form\DeactivateMemberInput;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * HTTP adapter for the member activation-state transitions (LRA-211):
 * deactivating an active member via a confirmation dialog that requires a
 * reason, and reactivating a deactivated one. A separate controller from
 * {@see MemberDetailController} because that controller already owns three
 * card flows and is ~900 lines; activation state is a different reason to
 * change.
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

    private const string UUID_V7_REGEX
        = '[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}';

    private const string MEMBER_NOT_FOUND_MESSAGE = 'Member not found.';

    private const string TEMPLATE_DEACTIVATE_DIALOG = 'households/detail/_deactivate_dialog.html.twig';

    public function __construct(
        // Consumed by the DispatchesHouseholdMessages trait at $this->queryBus.
        private readonly MessageBusInterface $queryBus, // NOSONAR
        // Consumed by the DispatchesHouseholdMessages trait at $this->commandBus.
        private readonly MessageBusInterface $commandBus, // NOSONAR
    ) {
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
            } catch (MemberNotFound | HouseholdNotFound | InvalidHouseholdId | InvalidMemberId) {
                throw $this->createNotFoundException(self::MEMBER_NOT_FOUND_MESSAGE);
            }

            return $this->hxRedirectToMemberDetail($householdId, $memberId);
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
        }

        return $this->hxRedirectToMemberDetail($householdId, $memberId);
    }

    private function findDetailOrFail(string $householdId, string $memberId): MemberDetail
    {
        try {
            return $this->runQuery($householdId, $memberId);
        } catch (MemberNotFound | HouseholdNotFound | InvalidHouseholdId | InvalidMemberId) {
            throw $this->createNotFoundException(self::MEMBER_NOT_FOUND_MESSAGE);
        }
    }
}
