<?php

declare(strict_types=1);

namespace App\Households\Infrastructure\Http\Controller;

use App\Households\Application\Query\Port\PageOfMembers;
use App\Households\Application\Query\Port\SearchMembersCriteria;
use App\Households\Application\Query\SearchMembers;
use InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * HTTP adapter for the reusable Member Lookup dialog (LRA-46).
 *
 * Returns the HTML partial used by HTMX to fill the dialog body's
 * `#member-lookup-results` slot. The route is intentionally separate from
 * the View Members list partial (`households_members_table`) because:
 *   - The lookup uses a smaller page size (10) so the dialog stays compact.
 *   - The lookup never participates in URL push (no `hx-push-url`); it is a
 *     modal interaction whose URL must remain whatever page the operator is on.
 *   - The lookup row markup encodes selection payload attributes
 *     (`data-member-id`, `data-household-id`, `data-full-name`, `data-code`)
 *     rather than navigation links, so it cannot reuse the list-page table.
 *
 * The {@see SearchMembers} query and {@see SearchMembersCriteria} DTO are
 * reused unchanged from LRA-39 — the lookup is a different *view* of the same
 * read model, not a different query.
 */
final class MemberLookupController extends AbstractController
{
    use HandleTrait {
        handle as private dispatchQuery;
    }

    /**
     * Page size cap for the dialog. Smaller than the main list page so a
     * keyboard user can scan results without scrolling. Out-of-range values
     * submitted in the query string return HTTP 400 at the boundary rather
     * than silently clamping, which surfaces operator/integration bugs early.
     */
    public const int LOOKUP_PAGE_SIZE = 10;

    public function __construct(MessageBusInterface $queryBus)
    {
        $this->messageBus = $queryBus;
    }

    #[Route('/admin/users/_lookup', name: 'member_lookup_search', methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        try {
            $criteria = $this->buildCriteria($request);
        } catch (InvalidArgumentException $exception) {
            return new Response($exception->getMessage(), Response::HTTP_BAD_REQUEST);
        }

        /** @var PageOfMembers $page */
        $page = $this->runQuery($criteria);

        return $this->render('components/member_lookup_dialog/_results.html.twig', [
            'page' => $page,
            'criteria' => $criteria,
        ]);
    }

    private function runQuery(SearchMembersCriteria $criteria): PageOfMembers
    {
        $result = $this->dispatchQuery(new SearchMembers($criteria));

        if (!$result instanceof PageOfMembers) {
            throw new \LogicException(sprintf(
                'SearchMembers handler returned %s, expected %s.',
                get_debug_type($result),
                PageOfMembers::class,
            ));
        }

        return $result;
    }

    /**
     * Mirrors SearchMembersController::buildCriteria but with a stricter
     * default + hard cap on pageSize so the dialog stays bounded. Callers
     * may still pass `page` to paginate, but `pageSize` defaults to the
     * lookup cap and out-of-range values throw (caught above as a 400).
     */
    private function buildCriteria(Request $request): SearchMembersCriteria
    {
        $query = $request->query;

        $requestedPageSize = $query->getInt('pageSize', self::LOOKUP_PAGE_SIZE);
        if ($requestedPageSize < 1 || $requestedPageSize > self::LOOKUP_PAGE_SIZE) {
            throw new InvalidArgumentException(sprintf(
                'Member lookup: pageSize must be between 1 and %d.',
                self::LOOKUP_PAGE_SIZE,
            ));
        }

        return new SearchMembersCriteria(
            // Accept either `code` (the in-dialog form name) or the
            // longer `memberCode` (used by external API callers and the
            // existing functional test); first non-blank value wins.
            memberCode: self::stringOrNull($query->get('code')) ?? self::stringOrNull($query->get('memberCode')),
            lastName: self::stringOrNull($query->get('lastName')),
            firstName: self::stringOrNull($query->get('firstName')),
            phone: self::stringOrNull($query->get('phone')),
            email: self::stringOrNull($query->get('email')),
            page: max(1, $query->getInt('page', 1)),
            pageSize: $requestedPageSize,
        );
    }

    private static function stringOrNull(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
