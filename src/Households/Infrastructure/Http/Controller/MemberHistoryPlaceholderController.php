<?php

declare(strict_types=1);

namespace App\Households\Infrastructure\Http\Controller;

use App\Households\Infrastructure\Http\View\MemberHistoryView;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\EnumRequirement;
use Symfony\Component\Routing\Requirement\Requirement;

/**
 * HTTP adapter for the History card's "coming soon" tabs (LRA-206).
 *
 * Every history kind other than Transactions ({@see MemberHistoryView})
 * has no real bounded context yet, so this single route renders the
 * shared placeholder fragment for whichever kind the tab strip asks for.
 * `Transactions` is deliberately excluded from the route's
 * {@see EnumRequirement} — its real, lazy-loaded table stays on
 * `member_history_page` in {@see MemberDetailController}, so
 * `/history/transactions` 404s here rather than shadowing that route.
 *
 * Like {@see MemberDetailController::historyPage()}, this action does not
 * verify the (household, member) pair exists: the fragment is only ever
 * requested via HTMX from an already-resolved member detail page sitting
 * behind the admin firewall, so an existence check here would be a
 * wasted query with no operator-facing benefit.
 */
final class MemberHistoryPlaceholderController extends AbstractController
{
    #[Route(
        '/admin/users/{householdId}/{memberId}/history/{view}',
        name: 'member_history_view_placeholder',
        requirements: [
            'householdId' => Requirement::UUID_V7,
            'memberId' => Requirement::UUID_V7,
            'view' => new EnumRequirement([
                MemberHistoryView::Activities,
                MemberHistoryView::Memberships,
                MemberHistoryView::FacilityRentals,
                MemberHistoryView::EquipmentRentals,
                MemberHistoryView::PosPurchases,
            ]),
        ],
        methods: ['GET'],
    )]
    public function __invoke(string $householdId, string $memberId, MemberHistoryView $view): Response
    {
        return $this->render('households/detail/_card_history_view_placeholder.html.twig', [
            'view' => $view,
        ]);
    }
}
