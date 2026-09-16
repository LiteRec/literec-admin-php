<?php

declare(strict_types=1);

namespace App\Households\Infrastructure\Http\Controller;

use App\Households\Application\Command\SplitMember;
use App\Households\Application\Query\Port\MemberDetail;
use App\Households\Domain\Exception\HouseholdNotFound;
use App\Households\Domain\Exception\InvalidHouseholdId;
use App\Households\Domain\Exception\InvalidMemberId;
use App\Households\Domain\Exception\InvalidPersonName;
use App\Households\Domain\Exception\MemberNotFound;
use App\Households\Domain\Exception\SplitSelectionEmpty;
use App\Households\Domain\ValueObject\MemberId;
use App\Households\Infrastructure\Http\Form\AppliesPersonNameErrors;
use App\Households\Infrastructure\Http\Form\SplitMemberFormType;
use App\Households\Infrastructure\Http\Form\SplitMemberInput;
use App\Shared\Domain\Exception\SharedDomainException;
use LogicException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * HTTP adapter for the split-member dialog (LRA-209): confirming which
 * Transaction History rows to split off the currently-viewed member into
 * a newly created member in the same household, and submitting the
 * split. Kept separate from {@see MemberDetailController} so that
 * controller does not keep growing, mirroring {@see MergeMembersController}.
 */
final class SplitMemberController extends AbstractController
{
    use AppliesPersonNameErrors;
    use DispatchesHouseholdMessages;

    private const string UUID_V7_REGEX
        = '[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}';

    private const string MEMBER_NOT_FOUND_MESSAGE = 'Member not found.';

    private const string NO_TRANSACTIONS_SELECTED_MESSAGE = 'Select at least one transaction to split.';

    public function __construct(
        private readonly MessageBusInterface $queryBus,
        private readonly MessageBusInterface $commandBus,
    ) {
    }

    private function queryBus(): MessageBusInterface
    {
        return $this->queryBus;
    }

    private function commandBus(): MessageBusInterface
    {
        return $this->commandBus;
    }

    #[Route(
        '/admin/users/{householdId}/{memberId}/split',
        name: 'member_split_form',
        requirements: [
            'householdId' => self::UUID_V7_REGEX,
            'memberId'    => self::UUID_V7_REGEX,
        ],
        methods: ['GET'],
    )]
    public function form(string $householdId, string $memberId, Request $request): Response
    {
        /** @var list<string> $transactionIds */
        $transactionIds = $request->query->all('transactionIds');

        if ($transactionIds === []) {
            throw new BadRequestHttpException(self::NO_TRANSACTIONS_SELECTED_MESSAGE);
        }

        $source = $this->loadDetailOr404($householdId, $memberId);

        $input = new SplitMemberInput();
        $input->transactionIds = $transactionIds;
        $input->firstName = $source->profile->firstName;
        $input->middleName = $source->profile->middleName;
        $input->lastName = $source->profile->lastName;
        $input->suffix = $source->profile->suffix;
        $splitForm = $this->createForm(SplitMemberFormType::class, $input);

        return $this->render('households/detail/_split_member_dialog.html.twig', [
            'source' => $source,
            'form' => $splitForm->createView(),
            'transactionCount' => count($transactionIds),
        ]);
    }

    #[Route(
        '/admin/users/{householdId}/{memberId}/split',
        name: 'member_split',
        requirements: [
            'householdId' => self::UUID_V7_REGEX,
            'memberId'    => self::UUID_V7_REGEX,
        ],
        methods: ['POST'],
    )]
    public function submit(string $householdId, string $memberId, Request $request): Response
    {
        $input = new SplitMemberInput();
        $splitForm = $this->createForm(SplitMemberFormType::class, $input);
        $splitForm->handleRequest($request);

        if (!$splitForm->isSubmitted() || !$splitForm->isValid()) {
            return $this->reRenderForm($householdId, $memberId, $splitForm);
        }

        $newMemberId = $this->trySplit($householdId, $memberId, $input, $splitForm);

        if ($newMemberId === null) {
            return $this->reRenderForm($householdId, $memberId, $splitForm);
        }

        return $this->hxRedirectTo($householdId, $newMemberId);
    }

    /**
     * Dispatches {@see SplitMember} and returns the new member's id, or
     * null after populating $form with a field/form-level error. Split
     * out of {@see self::submit()} to keep that method's return count
     * under SonarCloud's php:S1142 threshold.
     *
     * @template TData
     *
     * @param FormInterface<TData> $form
     */
    private function trySplit(
        string $householdId,
        string $memberId,
        SplitMemberInput $input,
        FormInterface $form,
    ): ?MemberId {
        try {
            $newMemberId = $this->dispatchCommandUnwrappingWithResult(new SplitMember(
                householdId: $householdId,
                sourceMemberId: $memberId,
                firstName: (string) $input->firstName,
                lastName: (string) $input->lastName,
                middleName: $input->middleName,
                suffix: $input->suffix,
                email: $input->email,
                phone: $input->phone,
                transactionIds: $input->transactionIds,
                reason: $input->reason,
                memberCode: null,
            ));
        } catch (MemberNotFound | HouseholdNotFound | InvalidHouseholdId | InvalidMemberId) {
            throw $this->createNotFoundException(self::MEMBER_NOT_FOUND_MESSAGE);
        } catch (InvalidPersonName $exception) {
            $this->applyNameErrorToForm($form, $exception);

            return null;
        } catch (SplitSelectionEmpty | SharedDomainException $exception) {
            $form->addError(new FormError($exception->getMessage()));

            return null;
        }

        if (!$newMemberId instanceof MemberId) {
            throw new LogicException(sprintf(
                'SplitMember handler returned %s, expected %s.',
                get_debug_type($newMemberId),
                MemberId::class,
            ));
        }

        return $newMemberId;
    }

    private function hxRedirectTo(string $householdId, MemberId $newMemberId): Response
    {
        $response = new Response(null, Response::HTTP_OK);
        $response->headers->set('HX-Redirect', $this->generateUrl('member_detail', [
            'householdId' => $householdId,
            'memberId' => $newMemberId->value,
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
    private function reRenderForm(string $householdId, string $memberId, FormInterface $form): Response
    {
        $source = $this->loadDetailOr404($householdId, $memberId);
        $data = $form->getData();
        $transactionCount = $data instanceof SplitMemberInput ? count($data->transactionIds) : 0;

        return $this->render(
            'households/detail/_split_member_dialog.html.twig',
            [
                'source' => $source,
                'form' => $form->createView(),
                'transactionCount' => $transactionCount,
            ],
            new Response(null, Response::HTTP_UNPROCESSABLE_ENTITY),
        );
    }
}
