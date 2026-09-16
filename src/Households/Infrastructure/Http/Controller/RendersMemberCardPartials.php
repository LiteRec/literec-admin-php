<?php

declare(strict_types=1);

namespace App\Households\Infrastructure\Http\Controller;

use App\Households\Application\Query\Port\MemberDetail;
use App\Households\Domain\Exception\HouseholdNotFound;
use App\Households\Domain\Exception\InvalidHouseholdId;
use App\Households\Domain\Exception\InvalidMemberId;
use App\Households\Domain\Exception\MemberNotFound;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Shared "load the member detail projection or 404" and "re-render a
 * card's edit partial" steps for the Households HTTP controllers that
 * drive the member detail page's per-card edit flows. Extracted from
 * {@see MemberDetailController} (LRA-235) so
 * {@see MemberProfileCardController}, {@see MemberContactCardController},
 * {@see HouseholdAddressCardController} and
 * {@see MemberResidencyCardController} do not each duplicate the same
 * four-exception 404 mapping and edit-partial re-render shape — keeps the
 * SonarCloud new-code duplication gate under 3%.
 *
 * Requires the consuming controller to be a Symfony
 * {@see \Symfony\Bundle\FrameworkBundle\Controller\AbstractController}
 * (for `createNotFoundException()` / `render()`) and to `use`
 * {@see DispatchesHouseholdMessages} (for `runQuery()`).
 */
trait RendersMemberCardPartials
{
    private const string UUID_V7_REGEX
        = '[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}';

    private const string MEMBER_NOT_FOUND_MESSAGE = 'Member not found.';

    /**
     * Loads the member detail projection or throws a 404. The four
     * domain exceptions {@see DispatchesHouseholdMessages::runQuery()} can
     * surface — an unknown or malformed household or member id — all mean
     * the same thing to the client: there is nothing at this URL.
     */
    private function findDetailOrFail(string $householdId, string $memberId): MemberDetail
    {
        try {
            return $this->runQuery($householdId, $memberId);
        } catch (MemberNotFound | HouseholdNotFound | InvalidHouseholdId | InvalidMemberId) {
            throw $this->createNotFoundException(self::MEMBER_NOT_FOUND_MESSAGE);
        }
    }

    /**
     * Re-renders a card's edit-mode partial at the given HTTP status with
     * the form (and its errors) intact. The card edit partials share an
     * identical shape — form view plus the (householdId, memberId) tuple
     * the template needs for its action URLs — so they differ only by
     * template.
     *
     * @template TData
     *
     * @param FormInterface<TData> $form
     */
    private function renderEditPartial(
        string $template,
        FormInterface $form,
        string $householdId,
        string $memberId,
        int $status,
    ): Response {
        return $this->render(
            $template,
            [
                'form' => $form->createView(),
                'householdId' => $householdId,
                'memberId' => $memberId,
            ],
            new Response(null, $status),
        );
    }
}
