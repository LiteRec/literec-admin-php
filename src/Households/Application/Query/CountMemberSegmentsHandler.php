<?php

declare(strict_types=1);

namespace App\Households\Application\Query;

use App\Households\Application\Query\Port\MemberReadModel;
use App\Households\Application\Query\Port\MemberSegmentCounts;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final class CountMemberSegmentsHandler
{
    public function __construct(
        private readonly MemberReadModel $readModel,
    ) {
    }

    public function __invoke(CountMemberSegments $query): MemberSegmentCounts
    {
        return $this->readModel->segmentCounts($query->q);
    }
}
