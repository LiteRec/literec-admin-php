<?php

declare(strict_types=1);

namespace App\Households\Infrastructure\Http\Controller;

use App\Households\Application\Command\AnonymizeMember;
use App\Households\Application\Query\Port\MemberDetail;
use App\Households\Domain\Exception\HouseholdNotFound;
use App\Households\Domain\Exception\InvalidHouseholdId;
use App\Households\Domain\Exception\InvalidMemberId;
use App\Households\Domain\Exception\MemberAlreadyMerged;
use App\Households\Domain\Exception\MemberIsAnonymized;
use App\Households\Domain\Exception\MemberNotFound;
use App\Households\Infrastructure\Http\Form\AnonymizeMemberFormType;
use App\Households\Infrastructure\Http\Form\AnonymizeMemberInput;
use App\Households\Infrastructure\Security\HouseholdsVoter;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * HTTP adapter for member anonymization (LRA-212): an irreversible PII
 * scrub gated behind a confirmation dialog that requires the operator to
 * type the member's full name and tick an acknowledgement box, both
 * re-checked server-side by {@see AnonymizeMemberFormType}. A separate
 * controller from {@see MemberDetailController} and
 * {@see MemberLifecycleController} — anonymization is a distinct,
 * one-way action with its own confirmation contract, not a reason to grow
 * either of those classes.
 *
 * Success returns an `HX-Redirect` to the member detail page, exactly
 * like {@see MemberLifecycleController}'s deactivate/reactivate actions:
 * the header badge, Profile card danger zone, and Household roster all
 * need to re-render from the scrubbed read model, so a full navigation
 * avoids a stale-partial problem rather than solving it with more
 * out-of-band swaps.
 *
 * Only the household and member ids are logged on success — never the
 * (now-placeholder) name — matching the codebase-wide "no PII in logs"
 * convention.
 */
final class AnonymizeMemberController extends AbstractController
{
    use DispatchesHouseholdMessages;
    use RedirectsToMemberDetail;

    private const string UUID_V7_REGEX
        = '[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}';

    private const string MEMBER_NOT_FOUND_MESSAGE = 'Member not found.';

    private const string TEMPLATE_ANONYMIZE_DIALOG = 'households/detail/_anonymize_dialog.html.twig';

    // Values passed to the dialog template as `blockedReason` when the GET
    // route finds the member already in a terminal state the action
    // cannot apply to — neither has a form to render.
    private const string BLOCKED_ANONYMIZED = 'anonymized';
    private const string BLOCKED_MERGED = 'merged';

    public function __construct(
        private readonly MessageBusInterface $queryBus,
        private readonly MessageBusInterface $commandBus,
        private readonly LoggerInterface $logger,
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
     * Renders the Anonymize Member confirmation dialog, pre-populated
     * with the member's current full name so {@see AnonymizeMemberFormType}
     * can check the typed confirmation against it. Returns 409 with a
     * notice (no form) when the member is already anonymized, or already
     * merged into another record — neither has an undo/anonymize path to
     * offer, and a merged record would otherwise reach
     * {@see \App\Households\Domain\Household::anonymizeMember()} and
     * throw {@see MemberAlreadyMerged}.
     */
    #[Route(
        '/admin/users/{householdId}/{memberId}/anonymize',
        name: 'member_anonymize_form',
        requirements: [
            'householdId' => self::UUID_V7_REGEX,
            'memberId'    => self::UUID_V7_REGEX,
        ],
        methods: ['GET'],
    )]
    #[IsGranted(HouseholdsVoter::ANONYMIZE_MEMBER)]
    public function anonymizeForm(string $householdId, string $memberId): Response
    {
        $detail = $this->findDetailOrFail($householdId, $memberId);

        if ($detail->profile->anonymizedAtIso !== null) {
            return $this->render(
                self::TEMPLATE_ANONYMIZE_DIALOG,
                ['blockedReason' => self::BLOCKED_ANONYMIZED],
                new Response(null, Response::HTTP_CONFLICT),
            );
        }

        if ($detail->profile->mergedIntoMemberId !== null) {
            return $this->render(
                self::TEMPLATE_ANONYMIZE_DIALOG,
                ['blockedReason' => self::BLOCKED_MERGED],
                new Response(null, Response::HTTP_CONFLICT),
            );
        }

        return $this->render(
            self::TEMPLATE_ANONYMIZE_DIALOG,
            $this->dialogContext($householdId, $memberId, $detail, $this->buildForm($detail)),
        );
    }

    /**
     * Handles the Anonymize Member dialog submission. An invalid
     * confirmation (blank, wrong name, unchecked box, or missing/invalid
     * CSRF token) re-renders the dialog at 422; a valid one dispatches
     * {@see AnonymizeMember} and redirects on success. A concurrent
     * anonymize between the GET and this POST surfaces as a 409 with the
     * dialog re-rendered, rather than a 500.
     */
    #[Route(
        '/admin/users/{householdId}/{memberId}/anonymize',
        name: 'member_anonymize_submit',
        requirements: [
            'householdId' => self::UUID_V7_REGEX,
            'memberId'    => self::UUID_V7_REGEX,
        ],
        methods: ['POST'],
    )]
    #[IsGranted(HouseholdsVoter::ANONYMIZE_MEMBER)]
    public function anonymizeSubmit(string $householdId, string $memberId, Request $request): Response
    {
        $detail = $this->findDetailOrFail($householdId, $memberId);
        $form = $this->buildForm($detail);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->dispatchCommandUnwrapping(new AnonymizeMember(
                    householdId: $householdId,
                    memberId: $memberId,
                ));

                $this->logger->info('Member anonymized.', [
                    'householdId' => $householdId,
                    'memberId' => $memberId,
                ]);

                return $this->hxRedirectToMemberDetail($householdId, $memberId);
            } catch (MemberIsAnonymized $exception) {
                $form->addError(new FormError($exception->getMessage()));

                return $this->render(
                    self::TEMPLATE_ANONYMIZE_DIALOG,
                    $this->dialogContext($householdId, $memberId, $detail, $form),
                    new Response(null, Response::HTTP_CONFLICT),
                );
            } catch (MemberAlreadyMerged $exception) {
                // The Users list and Profile card only hide the Anonymize
                // action for an already-merged member; neither stops a
                // dialog opened before a concurrent merge from being
                // submitted. Surface it as a form error like
                // MemberLifecycleController::deactivateSubmit() does for
                // the same exception, rather than letting it escape as a
                // 500.
                $form->addError(new FormError($exception->getMessage()));
            }
        }

        return $this->render(
            self::TEMPLATE_ANONYMIZE_DIALOG,
            $this->dialogContext($householdId, $memberId, $detail, $form),
            new Response(null, Response::HTTP_UNPROCESSABLE_ENTITY),
        );
    }

    /**
     * @return FormInterface<AnonymizeMemberInput>
     */
    private function buildForm(MemberDetail $detail): FormInterface
    {
        return $this->createForm(AnonymizeMemberFormType::class, new AnonymizeMemberInput(), [
            'expected_confirmation' => $detail->profile->fullName,
        ]);
    }

    /**
     * @param FormInterface<AnonymizeMemberInput> $form
     *
     * @return array{blockedReason: null, form: mixed, householdId: string, memberId: string, memberName: string}
     */
    private function dialogContext(
        string $householdId,
        string $memberId,
        MemberDetail $detail,
        FormInterface $form,
    ): array {
        return [
            'blockedReason' => null,
            'form' => $form->createView(),
            'householdId' => $householdId,
            'memberId' => $memberId,
            'memberName' => $detail->profile->fullName,
        ];
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
