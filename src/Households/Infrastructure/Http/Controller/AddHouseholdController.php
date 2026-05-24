<?php

declare(strict_types=1);

namespace App\Households\Infrastructure\Http\Controller;

use App\Households\Application\Command\AddMemberToHousehold;
use App\Households\Application\Command\RegisterHousehold;
use App\Households\Domain\Exception\DuplicateMemberCode;
use App\Households\Domain\Exception\DuplicateMemberId;
use App\Households\Domain\Exception\HouseholdNotFound;
use App\Households\Domain\Exception\HouseholdsDomainException;
use App\Households\Domain\Households;
use App\Households\Domain\ValueObject\HouseholdId;
use App\Households\Domain\ValueObject\MemberId;
use App\Households\Infrastructure\Http\Form\AddMemberFormType;
use App\Households\Infrastructure\Http\Form\AddMemberInput;
use App\Households\Infrastructure\Http\Form\RegisterHouseholdFormType;
use App\Households\Infrastructure\Http\Form\RegisterHouseholdInput;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

/**
 * HTTP adapter for the "New Household" / "Add Member" HTMX dialogs (LRA-40).
 *
 * Routes:
 *   - GET  /admin/users/new                                        — render Register Household dialog
 *   - POST /admin/users/new                                        — submit Register Household
 *   - GET  /admin/users/{householdId}/members/new                  — render Add Member dialog
 *   - POST /admin/users/{householdId}/members/new                  — submit Add Member
 *
 * The controller stays thin: build the form, dispatch the command DTO via
 * the command bus, translate domain failures into either inline form errors
 * (HTTP 422) or stable status codes (404 for unknown household), and on
 * success return a `HX-Redirect` header so HTMX performs a full navigation
 * to the member-detail page. The redirect target (`/admin/users/{h}/{m}`)
 * matches the row href already produced by the list page; the LRA-41 route
 * fills in the destination later.
 */
final class AddHouseholdController extends AbstractController
{
    use HandleTrait {
        handle as private dispatchCommand;
    }

    private const string UUID_V7_REGEX = '[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}';

    public function __construct(
        MessageBusInterface $commandBus,
        private readonly Households $households,
    ) {
        $this->messageBus = $commandBus;
    }

    #[Route('/admin/users/new', name: 'households_new_form', methods: ['GET'])]
    public function newForm(): Response
    {
        $form = $this->createForm(RegisterHouseholdFormType::class, new RegisterHouseholdInput());

        return $this->render('households/new/_dialog.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/admin/users/new', name: 'households_new_submit', methods: ['POST'])]
    public function newSubmit(Request $request): Response
    {
        $input = new RegisterHouseholdInput();
        $form = $this->createForm(RegisterHouseholdFormType::class, $input);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->render(
                'households/new/_dialog.html.twig',
                ['form' => $form->createView()],
                new Response(null, Response::HTTP_UNPROCESSABLE_ENTITY),
            );
        }

        try {
            $householdId = $this->runRegister($input);
        } catch (HouseholdsDomainException $exception) {
            $this->applyDomainErrorToForm($form, $exception);

            return $this->render(
                'households/new/_dialog.html.twig',
                ['form' => $form->createView()],
                new Response(null, Response::HTTP_UNPROCESSABLE_ENTITY),
            );
        }

        // The command handler returns the HouseholdId; we also need the
        // newly-created member id to build the redirect target. The handler
        // does not currently surface the MemberId, so we look it up via the
        // already-resolved HouseholdId — the primary member is the only
        // member on a freshly-registered household.
        $memberId = $this->primaryMemberIdOf($householdId);

        return $this->hxRedirectTo($householdId, $memberId);
    }

    #[Route(
        '/admin/users/{householdId}/members/new',
        name: 'households_member_new_form',
        requirements: ['householdId' => self::UUID_V7_REGEX],
        methods: ['GET'],
    )]
    public function memberNewForm(string $householdId): Response
    {
        try {
            $this->households->findById(HouseholdId::fromString($householdId));
        } catch (HouseholdNotFound) {
            throw $this->createNotFoundException('Household not found.');
        }

        $form = $this->createForm(AddMemberFormType::class, new AddMemberInput());

        return $this->render('households/new/_member_dialog.html.twig', [
            'form' => $form->createView(),
            'householdId' => $householdId,
        ]);
    }

    #[Route(
        '/admin/users/{householdId}/members/new',
        name: 'households_member_new_submit',
        requirements: ['householdId' => self::UUID_V7_REGEX],
        methods: ['POST'],
    )]
    public function memberNewSubmit(string $householdId, Request $request): Response
    {
        $input = new AddMemberInput();
        $form = $this->createForm(AddMemberFormType::class, $input);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->render(
                'households/new/_member_dialog.html.twig',
                ['form' => $form->createView(), 'householdId' => $householdId],
                new Response(null, Response::HTTP_UNPROCESSABLE_ENTITY),
            );
        }

        try {
            $memberId = $this->runAddMember($householdId, $input);
        } catch (HouseholdNotFound) {
            throw $this->createNotFoundException('Household not found.');
        } catch (HouseholdsDomainException $exception) {
            $this->applyDomainErrorToForm($form, $exception);

            return $this->render(
                'households/new/_member_dialog.html.twig',
                ['form' => $form->createView(), 'householdId' => $householdId],
                new Response(null, Response::HTTP_UNPROCESSABLE_ENTITY),
            );
        }

        return $this->hxRedirectTo(HouseholdId::fromString($householdId), $memberId);
    }

    private function runRegister(RegisterHouseholdInput $input): HouseholdId
    {
        $command = new RegisterHousehold(
            householdName: (string) $input->householdName,
            firstName: (string) $input->firstName,
            lastName: (string) $input->lastName,
            middleName: $input->middleName,
            suffix: $input->suffix,
            dobIso: (string) $input->dobIso,
            genderCode: (string) $input->genderCode,
            email: (string) $input->email,
            phone: (string) $input->phone,
            residencyStatusCode: (string) $input->residencyStatusCode,
            memberCode: $input->memberCode,
            street: (string) $input->street,
            unit: $input->unit,
            city: (string) $input->city,
            state: (string) $input->state,
            postalCode: (string) $input->postalCode,
            country: (string) $input->country,
        );

        $result = $this->dispatchCommandUnwrapping($command);

        if (!$result instanceof HouseholdId) {
            throw new \LogicException(sprintf(
                'RegisterHousehold handler returned %s, expected %s.',
                get_debug_type($result),
                HouseholdId::class,
            ));
        }

        return $result;
    }

    private function runAddMember(string $householdId, AddMemberInput $input): MemberId
    {
        $command = new AddMemberToHousehold(
            householdId: $householdId,
            firstName: (string) $input->firstName,
            lastName: (string) $input->lastName,
            middleName: $input->middleName,
            suffix: $input->suffix,
            dobIso: (string) $input->dobIso,
            genderCode: (string) $input->genderCode,
            email: (string) $input->email,
            phone: (string) $input->phone,
            residencyStatusCode: (string) $input->residencyStatusCode,
            memberCode: $input->memberCode,
            isPrimary: $input->isPrimary,
        );

        $result = $this->dispatchCommandUnwrapping($command);

        if (!$result instanceof MemberId) {
            throw new \LogicException(sprintf(
                'AddMemberToHousehold handler returned %s, expected %s.',
                get_debug_type($result),
                MemberId::class,
            ));
        }

        return $result;
    }

    /**
     * Messenger wraps handler exceptions in HandlerFailedException; unwrap
     * to surface the original domain exception to the caller.
     */
    private function dispatchCommandUnwrapping(object $command): mixed
    {
        try {
            return $this->dispatchCommand($command);
        } catch (HandlerFailedException $wrapper) {
            $nested = $wrapper->getPrevious();
            if ($nested instanceof Throwable) {
                throw $nested;
            }
            throw $wrapper;
        }
    }

    /**
     * The RegisterHousehold handler returns the HouseholdId only; the
     * member-detail redirect target also requires the primary member's id.
     * A freshly-registered household has exactly one (primary) member by
     * construction, so loading the aggregate via its repository port is
     * cheap and avoids growing the handler return type just for the UI.
     */
    private function primaryMemberIdOf(HouseholdId $id): MemberId
    {
        $household = $this->households->findById($id);

        foreach ($household->members() as $member) {
            if ($member->isPrimary()) {
                return $member->id();
            }
        }

        throw new \LogicException(
            'Freshly registered household has no primary member; aggregate invariant violated.',
        );
    }

    private function hxRedirectTo(HouseholdId $householdId, MemberId $memberId): Response
    {
        $target = $this->generateUrl('member_detail', [
            'householdId' => $householdId->value,
            'memberId'    => $memberId->value,
        ]);

        $response = new Response(null, Response::HTTP_OK);
        $response->headers->set('HX-Redirect', $target);

        return $response;
    }

    /**
     * Maps a domain exception to a form error. Code-collision exceptions
     * map to the `memberCode` field; everything else surfaces as a
     * form-level error so the operator sees it at the top of the dialog.
     *
     * @template TData
     * @param FormInterface<TData> $form
     */
    private function applyDomainErrorToForm(
        FormInterface $form,
        HouseholdsDomainException $exception,
    ): void {
        if (
            $exception instanceof DuplicateMemberCode
            && $form->has('memberCode')
        ) {
            $form->get('memberCode')->addError(new FormError($exception->getMessage()));

            return;
        }

        if ($exception instanceof DuplicateMemberId) {
            $form->addError(new FormError(
                'A member with this identity already exists; refresh and retry.',
            ));

            return;
        }

        $form->addError(new FormError($exception->getMessage()));
    }
}
