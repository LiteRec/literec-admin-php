<?php

declare(strict_types=1);

namespace App\Households\Infrastructure\Http\Controller;

use App\Households\Application\Command\ChangeMemberResidency;
use App\Households\Application\Query\Port\MemberDetail;
use App\Households\Domain\Exception\HouseholdNotFound;
use App\Households\Domain\Exception\InvalidHouseholdId;
use App\Households\Domain\Exception\InvalidMemberId;
use App\Households\Domain\Exception\MemberNotFound;
use App\Households\Infrastructure\Http\Form\ChangeMemberResidencyFormType;
use App\Households\Infrastructure\Http\Form\ChangeMemberResidencyInput;
use App\Shared\Domain\Exception\SharedDomainException;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * HTTP adapter for the member detail page's Residency sub-card change
 * flow (LRA-44): an HTMX-swapped change form pre-populated with the
 * member's current status, and a POST that re-dispatches the read query
 * and returns the read partial on success. Split out of
 * {@see MemberDetailController} (LRA-235) — the Residency sub-card is one
 * of four independently editable cards on that page, each a separate
 * reason to change.
 *
 * The controller stays thin: dispatches {@see \App\Households\Application\Query\GetMemberDetail} via the
 * `query.bus` and {@see ChangeMemberResidency} via the `command.bus`,
 * catches the domain exceptions that bubble out of either bus, and
 * translates them to HTTP status codes (404 for missing aggregates, 422
 * with inline form errors for validation failures).
 */
final class MemberResidencyCardController extends AbstractController
{
    use DispatchesHouseholdMessages;
    use RendersMemberCardPartials;

    private const string TEMPLATE_RESIDENCY_EDIT = 'households/detail/_residency_sub_card_edit.html.twig';

    public function __construct(
        private readonly MessageBusInterface $queryBus,
        private readonly MessageBusInterface $commandBus,
        private readonly ClockInterface $clock,
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
        $detail = $this->findDetailOrFail($householdId, $memberId);

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
}
