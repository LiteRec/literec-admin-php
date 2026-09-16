<?php

declare(strict_types=1);

namespace App\Households\Infrastructure\Http\Controller;

use App\Households\Application\Command\MergeMembers;
use App\Households\Application\Query\Port\MemberDetail;
use App\Households\Domain\Exception\CannotMergeMemberIntoItself;
use App\Households\Domain\Exception\HouseholdNotFound;
use App\Households\Domain\Exception\InactiveSurvivorCannotAcceptMerge;
use App\Households\Domain\Exception\InvalidHouseholdId;
use App\Households\Domain\Exception\InvalidMemberId;
use App\Households\Domain\Exception\MemberAlreadyMerged;
use App\Households\Domain\Exception\MemberNotFound;
use App\Households\Infrastructure\Http\Form\MergeMembersFormType;
use App\Households\Infrastructure\Http\Form\MergeMembersInput;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * HTTP adapter for the member-merge dialog (LRA-208): confirming which
 * duplicate to merge into the currently-viewed survivor, and submitting
 * the merge. Kept separate from {@see MemberDetailController} so that
 * controller does not keep growing.
 */
final class MergeMembersController extends AbstractController
{
    use DispatchesHouseholdMessages;

    private const string UUID_V7_REGEX
        = '[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}';

    private const string MEMBER_NOT_FOUND_MESSAGE = 'Member not found.';

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

    #[Route(
        '/admin/users/{householdId}/{memberId}/merge/confirm',
        name: 'member_merge_confirm_form',
        requirements: [
            'householdId' => self::UUID_V7_REGEX,
            'memberId'    => self::UUID_V7_REGEX,
        ],
        methods: ['GET'],
    )]
    public function confirm(string $householdId, string $memberId, Request $request): Response
    {
        $duplicateMemberId = (string) $request->query->get('duplicateMemberId', '');
        $duplicateHouseholdId = (string) $request->query->get('duplicateHouseholdId', '');

        $survivor = $this->loadDetailOr404($householdId, $memberId);
        $duplicate = $this->loadDetailOr404($duplicateHouseholdId, $duplicateMemberId);

        $input = new MergeMembersInput();
        $input->duplicateMemberId = $duplicateMemberId;
        $input->duplicateHouseholdId = $duplicateHouseholdId;
        $form = $this->createForm(MergeMembersFormType::class, $input);

        return $this->render('households/detail/_merge_confirm_dialog.html.twig', [
            'survivor' => $survivor,
            'duplicate' => $duplicate,
            'form' => $form->createView(),
        ]);
    }

    #[Route(
        '/admin/users/{householdId}/{memberId}/merge',
        name: 'member_merge_submit',
        requirements: [
            'householdId' => self::UUID_V7_REGEX,
            'memberId'    => self::UUID_V7_REGEX,
        ],
        methods: ['POST'],
    )]
    public function submit(string $householdId, string $memberId, Request $request): Response
    {
        $input = new MergeMembersInput();
        $form = $this->createForm(MergeMembersFormType::class, $input);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->reRenderConfirm($householdId, $memberId, $input, $form);
        }

        try {
            $this->dispatchCommandUnwrapping(new MergeMembers(
                survivorHouseholdId: $householdId,
                survivorMemberId: $memberId,
                duplicateMemberId: (string) $input->duplicateMemberId,
            ));
        } catch (MemberNotFound | HouseholdNotFound | InvalidHouseholdId | InvalidMemberId) {
            throw $this->createNotFoundException(self::MEMBER_NOT_FOUND_MESSAGE);
        } catch (CannotMergeMemberIntoItself | MemberAlreadyMerged | InactiveSurvivorCannotAcceptMerge $exception) {
            $form->addError(new FormError($exception->getMessage()));

            return $this->reRenderConfirm($householdId, $memberId, $input, $form);
        }

        $response = new Response(null, Response::HTTP_OK);
        $response->headers->set('HX-Redirect', $this->generateUrl('member_detail', [
            'householdId' => $householdId,
            'memberId' => $memberId,
        ]));

        return $response;
    }

    private function loadDetailOr404(string $householdId, string $memberId): MemberDetail
    {
        try {
            return $this->runQuery($householdId, $memberId);
        } catch (MemberNotFound | HouseholdNotFound | InvalidHouseholdId | InvalidMemberId) {
            throw $this->createNotFoundException(self::MEMBER_NOT_FOUND_MESSAGE);
        }
    }

    /**
     * @template TData
     *
     * @param FormInterface<TData> $form
     */
    private function reRenderConfirm(
        string $householdId,
        string $memberId,
        MergeMembersInput $input,
        FormInterface $form,
    ): Response {
        $survivor = $this->loadDetailOr404($householdId, $memberId);
        $duplicate = $this->loadDetailOr404(
            (string) $input->duplicateHouseholdId,
            (string) $input->duplicateMemberId,
        );

        return $this->render(
            'households/detail/_merge_confirm_dialog.html.twig',
            [
                'survivor' => $survivor,
                'duplicate' => $duplicate,
                'form' => $form->createView(),
            ],
            new Response(null, Response::HTTP_UNPROCESSABLE_ENTITY),
        );
    }
}
