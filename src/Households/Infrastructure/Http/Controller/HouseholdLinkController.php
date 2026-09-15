<?php

declare(strict_types=1);

namespace App\Households\Infrastructure\Http\Controller;

use App\Households\Application\Command\LinkMinorToHousehold;
use App\Households\Application\Command\WithdrawMinorFromHousehold;
use App\Households\Domain\Exception\CannotShareWithHomeHousehold;
use App\Households\Domain\Exception\HouseholdAlreadyLinked;
use App\Households\Domain\Exception\HouseholdNotFound;
use App\Households\Domain\Exception\InvalidHouseholdId;
use App\Households\Domain\Exception\InvalidMemberId;
use App\Households\Domain\Exception\InvariantViolation;
use App\Households\Domain\Exception\MemberNotAMinor;
use App\Households\Domain\Exception\MemberNotFound;
use App\Households\Domain\Households;
use App\Households\Domain\ValueObject\MemberId;
use App\Households\Infrastructure\Http\Form\LinkMinorToHouseholdFormType;
use App\Households\Infrastructure\Http\Form\LinkMinorToHouseholdInput;
use App\Households\Infrastructure\Http\Form\WithdrawMinorFromHouseholdFormType;
use App\Households\Infrastructure\Http\Form\WithdrawMinorFromHouseholdInput;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

/**
 * HTTP adapter for sharing a minor member with another household, and
 * withdrawing that share (LRA-210). Kept separate from
 * {@see MemberDetailController} so that controller does not keep growing.
 *
 * The "Link Shared Member" button (Household card) submits via a
 * JS-driven POST once the operator selects a member from the reusable
 * lookup dialog — there is no confirm step, unlike the merge flow. The
 * "Unlink" button (linked-households list, member header) is a plain HTMX
 * form post. Both submissions still go through a Symfony Form purely for
 * CSRF protection, matching the codebase-wide convention.
 *
 * Success responds with an HX-Redirect, mirroring
 * {@see AddHouseholdController::hxRedirectTo()}: linking sends the
 * operator to the child under the target household, unlinking sends them
 * back to the child under its home household. A validation or domain
 * failure re-renders {@see \App\Households\Infrastructure\Http\Controller\HouseholdLinkController}'s
 * shared error partial into the Household card's dedicated
 * `#household-link-error` region at 422 — no raw exception text reaches
 * the operator.
 */
final class HouseholdLinkController extends AbstractController
{
    private const string UUID_V7_REGEX
        = '[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}';

    private const string MEMBER_NOT_FOUND_MESSAGE = 'Member not found.';

    private const string GENERIC_LINK_FAILURE = 'The member could not be linked. Please try again.';

    private const string ERROR_TEMPLATE = 'households/detail/_household_link_error.html.twig';

    public function __construct(
        private readonly MessageBusInterface $commandBus,
        private readonly Households $households,
    ) {
    }

    #[Route(
        '/admin/users/{householdId}/members/link',
        name: 'household_member_link',
        requirements: ['householdId' => self::UUID_V7_REGEX],
        methods: ['POST'],
    )]
    public function link(string $householdId, Request $request): Response
    {
        $input = new LinkMinorToHouseholdInput();
        $form = $this->createForm(LinkMinorToHouseholdFormType::class, $input);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->errorResponse($this->firstFormErrorMessage($form));
        }

        $memberId = (string) $input->memberId;

        try {
            $this->dispatchCommandUnwrapping(new LinkMinorToHousehold($householdId, $memberId));
        } catch (HouseholdNotFound | MemberNotFound | InvalidHouseholdId | InvalidMemberId) {
            throw $this->createNotFoundException(self::MEMBER_NOT_FOUND_MESSAGE);
        } catch (
            MemberNotAMinor
            | HouseholdAlreadyLinked
            | CannotShareWithHomeHousehold
            | InvariantViolation $exception
        ) {
            return $this->errorResponse($exception->getMessage());
        }

        return $this->hxRedirectTo($householdId, $memberId);
    }

    #[Route(
        '/admin/users/{householdId}/members/{memberId}/unlink',
        name: 'household_member_unlink',
        requirements: [
            'householdId' => self::UUID_V7_REGEX,
            'memberId'    => self::UUID_V7_REGEX,
        ],
        methods: ['POST'],
    )]
    public function unlink(string $householdId, string $memberId, Request $request): Response
    {
        $form = $this->createForm(WithdrawMinorFromHouseholdFormType::class, new WithdrawMinorFromHouseholdInput());
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->errorResponse($this->firstFormErrorMessage($form));
        }

        try {
            $this->dispatchCommandUnwrapping(new WithdrawMinorFromHousehold($householdId, $memberId));
        } catch (HouseholdNotFound | MemberNotFound | InvalidHouseholdId | InvalidMemberId) {
            throw $this->createNotFoundException(self::MEMBER_NOT_FOUND_MESSAGE);
        }

        $home = $this->households->findByMemberId(MemberId::fromString($memberId));

        return $this->hxRedirectTo($home->id()->value, $memberId);
    }

    private function hxRedirectTo(string $householdId, string $memberId): Response
    {
        $response = new Response(null, Response::HTTP_OK);
        $response->headers->set('HX-Redirect', $this->generateUrl('member_detail', [
            'householdId' => $householdId,
            'memberId'    => $memberId,
        ]));

        return $response;
    }

    /**
     * Messenger wraps handler exceptions in HandlerFailedException; unwrap
     * to surface the original domain exception to the caller. Both command
     * handlers here return void, so this discards the dispatch result
     * rather than unwrapping one, unlike
     * {@see AddHouseholdController::dispatchCommandUnwrapping()}.
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

    private function errorResponse(string $message): Response
    {
        return $this->render(
            self::ERROR_TEMPLATE,
            ['message' => $message],
            new Response(null, Response::HTTP_UNPROCESSABLE_ENTITY),
        );
    }

    /**
     * @template TData
     *
     * @param FormInterface<TData> $form
     */
    private function firstFormErrorMessage(FormInterface $form): string
    {
        foreach ($form->getErrors(true) as $error) {
            return $error->getMessage();
        }

        return self::GENERIC_LINK_FAILURE;
    }
}
