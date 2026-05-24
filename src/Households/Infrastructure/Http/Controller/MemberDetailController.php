<?php

declare(strict_types=1);

namespace App\Households\Infrastructure\Http\Controller;

use App\Households\Application\Query\GetMemberDetail;
use App\Households\Application\Query\Port\MemberDetail;
use App\Households\Domain\Exception\HouseholdNotFound;
use App\Households\Domain\Exception\InvalidHouseholdId;
use App\Households\Domain\Exception\InvalidMemberId;
use App\Households\Domain\Exception\MemberNotFound;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

/**
 * HTTP adapter for the member detail page (LRA-41).
 *
 * Renders the composite shell that hosts the four card slots — Household
 * (LRA-42), Profile (LRA-43), Address & Residency (LRA-44), Transaction
 * History (LRA-45). The cards themselves are placeholder partials at this
 * point; this ticket ships the route, the breadcrumb, the header strip
 * and the slot scaffolding so the page renders cleanly while subsequent
 * tickets fill in card content.
 *
 * The controller stays thin: dispatches {@see GetMemberDetail} via the
 * `query.bus`, catches the domain exceptions that bubble out of the
 * read-model port, and translates them to a 404. Route requirements
 * enforce the UUID v7 shape of both identifiers so the value-object
 * factories should not throw; the catches on {@see InvalidHouseholdId}
 * and {@see InvalidMemberId} are defence in depth.
 */
final class MemberDetailController extends AbstractController
{
    use HandleTrait {
        handle as private dispatchQuery;
    }

    private const string UUID_V7_REGEX
        = '[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}';

    public function __construct(MessageBusInterface $queryBus)
    {
        $this->messageBus = $queryBus;
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
     * Dispatches the GetMemberDetail query and unwraps Messenger's
     * HandlerFailedException so the original domain exceptions reach the
     * caller. Domain exceptions cannot leak through HandleTrait's
     * protected handle() in their raw form because Messenger always
     * wraps handler failures.
     */
    private function runQuery(string $householdId, string $memberId): MemberDetail
    {
        try {
            $result = $this->dispatchQuery(new GetMemberDetail($householdId, $memberId));
        } catch (HandlerFailedException $wrapper) {
            $nested = $wrapper->getPrevious();
            if ($nested instanceof Throwable) {
                throw $nested;
            }
            throw $wrapper;
        }

        if (!$result instanceof MemberDetail) {
            throw new \LogicException(sprintf(
                'GetMemberDetail handler returned %s, expected %s.',
                get_debug_type($result),
                MemberDetail::class,
            ));
        }

        return $result;
    }
}
