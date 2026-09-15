<?php

declare(strict_types=1);

namespace App\Households\Infrastructure\Http\Controller;

use Symfony\Component\HttpFoundation\Response;

/**
 * Shared `HX-Redirect` helper for the Households HTTP controllers that,
 * on success, send the client to the member detail page for a full
 * navigation rather than an in-place HTMX swap. Extracted from
 * {@see AddHouseholdController::hxRedirectTo()} (LRA-211) so
 * {@see MemberLifecycleController} does not duplicate the same response
 * shape — keeps the SonarCloud new-code duplication gate under 3%.
 *
 * Requires the consuming controller to be a Symfony
 * {@see \Symfony\Bundle\FrameworkBundle\Controller\AbstractController}
 * (for `generateUrl()`).
 */
trait RedirectsToMemberDetail
{
    private function hxRedirectToMemberDetail(string $householdId, string $memberId): Response
    {
        $target = $this->generateUrl('member_detail', [
            'householdId' => $householdId,
            'memberId'    => $memberId,
        ]);

        $response = new Response(null, Response::HTTP_OK);
        $response->headers->set('HX-Redirect', $target);

        return $response;
    }
}
