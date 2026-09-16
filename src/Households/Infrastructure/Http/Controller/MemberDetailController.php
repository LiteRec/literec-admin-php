<?php

declare(strict_types=1);

namespace App\Households\Infrastructure\Http\Controller;

use App\Households\Application\Port\MemberTransactionHistory;
use App\Households\Application\Query\Port\MemberDetail;
use App\Households\Domain\Exception\HouseholdNotFound;
use App\Households\Domain\Exception\InvalidHouseholdId;
use App\Households\Domain\Exception\InvalidMemberId;
use App\Households\Domain\Exception\MemberNotFound;
use App\Households\Domain\ValueObject\HouseholdId as HouseholdIdVo;
use App\Households\Domain\ValueObject\MemberId as MemberIdVo;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * HTTP adapter for the member detail page (LRA-41): renders the composite
 * shell that hosts the four card slots — Household (LRA-42), Profile
 * (LRA-43), Address & Residency (LRA-44), Transaction History (LRA-45) —
 * serves the HTMX partial that swaps the three lower cards when the
 * active member changes, and serves the Transaction History card's
 * paginated fragment.
 *
 * Each card's own edit/submit flow lives in its own controller —
 * {@see MemberProfileCardController}, {@see MemberContactCardController},
 * {@see HouseholdAddressCardController}, {@see MemberResidencyCardController}
 * — split out of this class (LRA-235) because each card is a separate
 * reason to change; this controller owns only the page shell and the
 * cross-card concerns (page render, active-member switch, history
 * pagination).
 *
 * The controller stays thin: dispatches {@see \App\Households\Application\Query\GetMemberDetail} via the
 * `query.bus`, catches the domain exceptions that bubble out of it, and
 * translates them to HTTP status codes (404 for missing aggregates).
 * Route requirements enforce the UUID v7 shape of both identifiers so the
 * value-object factories should not throw; the catches on
 * {@see InvalidHouseholdId} and {@see InvalidMemberId} are defence in
 * depth.
 */
final class MemberDetailController extends AbstractController
{
    use DispatchesHouseholdMessages;

    private const string UUID_V7_REGEX
        = '[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}';

    /**
     * Default page size used by the Transaction History card when the
     * client does not specify one. Matched by the initial lazy-load
     * trigger emitted from {@see _card_history.html.twig}.
     */
    private const int HISTORY_DEFAULT_PAGE_SIZE = 20;

    /**
     * Hard cap on the Transaction History page size to keep a single
     * request bounded. Out-of-range values produce HTTP 400.
     */
    private const int HISTORY_MAX_PAGE_SIZE = 50;

    private const string MEMBER_NOT_FOUND_MESSAGE = 'Member not found.';

    /**
     * HTMX response header (LRA-153). Success responses set it so assets/app.js
     * can announce the outcome of an in-place update in the shared #lr-live
     * region without a focus change.
     */
    private const string HEADER_HX_TRIGGER = 'HX-Trigger';

    private const string HX_TRIGGER_MEMBER_LOADED = 'memberLoaded';

    public function __construct(
        private readonly MessageBusInterface $queryBus,
        private readonly MessageBusInterface $commandBus,
        private readonly MemberTransactionHistory $transactionHistory,
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
            throw $this->createNotFoundException(self::MEMBER_NOT_FOUND_MESSAGE);
        }

        return $this->render('households/detail.html.twig', [
            'detail' => $detail,
        ]);
    }

    /**
     * HTMX partial endpoint that returns the three lower cards
     * (Profile, Address, History) for the requested member, plus
     * out-of-band copies of everything else on the page that shows the
     * active member's identity (LRA-203): the document title, the shared
     * page head, the member header, and the Household card's roster
     * highlight. The Household card (LRA-42) wraps this endpoint to switch
     * the active member without re-rendering itself; HTMX swaps
     * `#member-cards-lower` with the response body, applies the OOB
     * fragments to their own ids, and the client-side `hx-push-url` keeps
     * the browser URL in sync with the new (householdId, memberId) tuple.
     * The Profile/Address/Residency cards' Cancel buttons also call this
     * endpoint for the currently active member; they receive the same OOB
     * copies, which is harmless since nothing has changed.
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
            throw $this->createNotFoundException(self::MEMBER_NOT_FOUND_MESSAGE);
        }

        $response = $this->render('households/detail/_lower_cards.html.twig', [
            'detail' => $detail,
            'oob'    => true,
        ]);
        $response->headers->set(self::HEADER_HX_TRIGGER, $this->memberLoadedTrigger($detail));

        return $response;
    }

    /**
     * Builds the HX-Trigger payload that names the freshly loaded member so
     * the shared #lr-live region can announce the context switch (LRA-153).
     */
    private function memberLoadedTrigger(MemberDetail $detail): string
    {
        $name = $detail->profile->fullName;

        return (string) json_encode(
            [self::HX_TRIGGER_MEMBER_LOADED => ['name' => $name]],
            JSON_THROW_ON_ERROR,
        );
    }

    /**
     * HTMX fragment endpoint that returns a single page of the member's
     * transaction history (LRA-45). The History card body lazy-loads
     * page 1 the first time the user expands the card; the "Load more"
     * button in the response triggers subsequent pages, each one
     * appended into the table via HTMX's `outerHTML` swap on the
     * button's `<tr>` placeholder.
     *
     * Page-size validation is enforced at the boundary: out-of-range
     * values return HTTP 400 rather than being silently clamped, so a
     * caller that asks for too much is told so instead of getting a
     * smaller answer than requested.
     *
     * The existence of the (household, member) pair is not pre-checked
     * here: the stub adapter naturally returns an empty page for an
     * unknown member, and the cost of an extra detail query per
     * scrolled page is wasteful. When a real Transactions ACL adapter
     * lands, it owns the unknown-member behaviour for its own backend.
     */
    #[Route(
        '/admin/users/{householdId}/{memberId}/history',
        name: 'member_history_page',
        requirements: [
            'householdId' => self::UUID_V7_REGEX,
            'memberId'    => self::UUID_V7_REGEX,
        ],
        methods: ['GET'],
    )]
    public function historyPage(string $householdId, string $memberId, Request $request): Response
    {
        $page = $request->query->getInt('page', 1);
        $pageSize = $request->query->getInt('pageSize', self::HISTORY_DEFAULT_PAGE_SIZE);

        if ($page < 1) {
            throw new BadRequestHttpException(
                sprintf('page must be >= 1, got %d.', $page),
            );
        }
        if ($pageSize < 1 || $pageSize > self::HISTORY_MAX_PAGE_SIZE) {
            throw new BadRequestHttpException(
                sprintf(
                    'pageSize must be between 1 and %d, got %d.',
                    self::HISTORY_MAX_PAGE_SIZE,
                    $pageSize,
                ),
            );
        }

        try {
            $householdIdVo = HouseholdIdVo::fromString($householdId);
            $memberIdVo = MemberIdVo::fromString($memberId);
        } catch (InvalidHouseholdId | InvalidMemberId) {
            // Route requirements already enforce UUID v7, so this is
            // defence in depth — surface as 404 to match the rest of
            // the member-scoped endpoints.
            throw $this->createNotFoundException(self::MEMBER_NOT_FOUND_MESSAGE);
        }

        $pageDto = $this->transactionHistory->page(
            $householdIdVo,
            $memberIdVo,
            $page,
            $pageSize,
        );

        return $this->render('households/detail/_card_history_rows.html.twig', [
            'householdId' => $householdId,
            'memberId' => $memberId,
            'page' => $pageDto,
        ]);
    }
}
