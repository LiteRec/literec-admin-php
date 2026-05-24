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
 * HTTP adapter for the members list page (LRA-39).
 *
 * Two routes share the same query dispatch:
 *   - `users_index` (`GET /admin/users`) renders the full page shell.
 *   - `households_members_table` (`GET /admin/users/_table`) returns only the
 *     table partial, used by HTMX to swap into `#members-table` when filters
 *     or pagination change.
 *
 * The controller is intentionally thin: it decodes the query string into a
 * primitive {@see SearchMembersCriteria}, wraps it in a {@see SearchMembers}
 * query, dispatches via the `query.bus`, and hands the {@see PageOfMembers}
 * projection to Twig. All business logic lives behind the read-model port.
 */
final class SearchMembersController extends AbstractController
{
    use HandleTrait {
        handle as private dispatchQuery;
    }

    public function __construct(
        MessageBusInterface $queryBus,
        private readonly bool $exportDuplicatesEnabled,
    ) {
        $this->messageBus = $queryBus;
    }

    #[Route('/admin/users', name: 'users_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        try {
            $criteria = $this->buildCriteria($request);
        } catch (InvalidArgumentException $exception) {
            return new Response($exception->getMessage(), Response::HTTP_BAD_REQUEST);
        }

        /** @var PageOfMembers $page */
        $page = $this->runQuery($criteria);

        return $this->render('households/list.html.twig', [
            'page' => $page,
            'criteria' => $criteria,
            'exportDuplicatesEnabled' => $this->exportDuplicatesEnabled,
        ]);
    }

    #[Route('/admin/users/_table', name: 'households_members_table', methods: ['GET'])]
    public function table(Request $request): Response
    {
        try {
            $criteria = $this->buildCriteria($request);
        } catch (InvalidArgumentException $exception) {
            return new Response($exception->getMessage(), Response::HTTP_BAD_REQUEST);
        }

        /** @var PageOfMembers $page */
        $page = $this->runQuery($criteria);

        return $this->render('households/list/_table.html.twig', [
            'page' => $page,
            'criteria' => $criteria,
        ]);
    }

    /**
     * Runs the SearchMembers query through the configured query bus.
     *
     * HandleTrait's protected handle() expects exactly one handler to have
     * processed the message; wrapping the dispatch in a small helper keeps
     * the action methods focused on HTTP concerns.
     */
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

    private function buildCriteria(Request $request): SearchMembersCriteria
    {
        $query = $request->query;

        return new SearchMembersCriteria(
            memberCode: self::stringOrNull($query->get('memberCode')),
            lastName: self::stringOrNull($query->get('lastName')),
            firstName: self::stringOrNull($query->get('firstName')),
            phone: self::stringOrNull($query->get('phone')),
            email: self::stringOrNull($query->get('email')),
            primaryOnly: self::boolish($query->get('primaryOnly')),
            includeDeleted: self::boolish($query->get('includeDeleted')),
            page: max(1, $query->getInt('page', 1)),
            pageSize: $query->getInt('pageSize', 20),
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

    private static function boolish(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (!is_string($value) && !is_int($value)) {
            return false;
        }

        return in_array((string) $value, ['1', 'true', 'on', 'yes'], true);
    }
}
