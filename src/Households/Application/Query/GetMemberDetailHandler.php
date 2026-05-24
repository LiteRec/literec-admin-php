<?php

declare(strict_types=1);

namespace App\Households\Application\Query;

use App\Households\Application\Query\Port\MemberDetail;
use App\Households\Application\Query\Port\MemberReadModel;
use App\Households\Domain\ValueObject\HouseholdId;
use App\Households\Domain\ValueObject\MemberId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final class GetMemberDetailHandler
{
    public function __construct(
        private readonly MemberReadModel $readModel,
    ) {
    }

    public function __invoke(GetMemberDetail $query): MemberDetail
    {
        $householdId = HouseholdId::fromString($query->householdId);
        $memberId = MemberId::fromString($query->memberId);

        return $this->readModel->memberDetail($householdId, $memberId);
    }
}
